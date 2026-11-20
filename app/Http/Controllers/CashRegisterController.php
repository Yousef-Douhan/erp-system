<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashShift;
use App\Services\AccountingService;
use App\Services\CashBook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * الخزائن: كل خزينة = مكان فيه نقد فعلي (درج كاشير، خزنة المدير...).
 * الرصيد لا يُعدَّل يدوياً أبداً؛ كل تغيير حركة مسجلة في دفتر الخزينة (CashBook).
 */
class CashRegisterController extends Controller
{
    public function index()
    {
        $registers = CashRegister::with(['branch:id,name', 'openShift.user:id,username'])
            ->orderByRaw("status = 'active' DESC")->orderBy('branch_id')->orderBy('name')
            ->get();

        // حركة اليوم لكل خزينة (وارد/صادر)
        $today = CashMovement::whereIn('cash_register_id', $registers->pluck('id'))
            ->whereDate('created_at', today())
            ->whereNotIn('type', ['carried_forward'])
            ->selectRaw("cash_register_id, SUM(CASE WHEN direction = 'in' THEN amount ELSE 0 END) AS cash_in, SUM(CASE WHEN direction = 'out' THEN amount ELSE 0 END) AS cash_out")
            ->groupBy('cash_register_id')
            ->get()->keyBy('cash_register_id');

        $active = $registers->where('status', 'active');
        $totals = [
            'balance' => (float) $active->sum('current_balance'),
            'in'      => (float) $today->sum('cash_in'),
            'out'     => (float) $today->sum('cash_out'),
            'open'    => $registers->filter(fn ($r) => $r->openShift)->count(),
        ];

        $branches = Branch::orderBy('name')->get(['id', 'name']);

        return view('cash_registers.index', compact('registers', 'today', 'totals', 'branches'));
    }

    /** كشف حساب الخزينة: كل الحركات مع الرصيد بعد كل حركة */
    public function show(Request $request, CashRegister $cashRegister)
    {
        $from = $request->date('from') ?? today()->subDays(29);
        $to = $request->date('to') ?? today();
        $type = $request->query('type');

        $base = CashMovement::where('cash_register_id', $cashRegister->id);

        $movements = (clone $base)
            ->with(['user:id,username'])
            ->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)
            ->when($type, fn ($q) => $q->where('type', $type))
            ->orderByDesc('id')
            ->paginate(40)->withQueryString();

        $period = (clone $base)->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)
            ->selectRaw("SUM(CASE WHEN direction = 'in' THEN amount ELSE 0 END) AS cash_in, SUM(CASE WHEN direction = 'out' THEN amount ELSE 0 END) AS cash_out")
            ->first();

        // الرصيد في بداية الفترة = رصيد آخر حركة قبلها
        $openingBalance = (float) ((clone $base)->whereDate('created_at', '<', $from)->orderByDesc('id')->value('balance_after') ?? 0);

        // ملخص الفترة حسب النوع
        $byType = (clone $base)->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)
            ->selectRaw("type, direction, COUNT(*) AS cnt, SUM(amount) AS total")
            ->groupBy('type', 'direction')->orderByDesc('total')->get();

        $cashRegister->load(['branch:id,name', 'openShift.user:id,username']);
        $others = CashRegister::where('status', 'active')->whereKeyNot($cashRegister->id)->orderBy('name')->get(['id', 'name', 'branch_id']);

        return view('cash_registers.show', [
            'register'       => $cashRegister,
            'movements'      => $movements,
            'from'           => $from,
            'to'             => $to,
            'type'           => $type,
            'cashIn'         => (float) ($period->cash_in ?? 0),
            'cashOut'        => (float) ($period->cash_out ?? 0),
            'openingBalance' => $openingBalance,
            'byType'         => $byType,
            'others'         => $others,
            'types'          => CashBook::TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id'       => 'required|exists:branches,id,deleted_at,NULL',
            'name'            => 'required|string|max:255',
            'opening_balance' => 'nullable|numeric|min:0',
        ]);
        $opening = round((float) ($validated['opening_balance'] ?? 0), 2);

        DB::transaction(function () use ($validated, $opening) {
            $register = CashRegister::create([
                'branch_id'       => $validated['branch_id'],
                'name'            => $validated['name'],
                'opening_balance' => $opening,
                'current_balance' => 0,
                'status'          => 'active',
            ]);

            if ($opening > 0) {
                CashBook::in($register, $opening, 'opening', 'الرصيد الافتتاحي عند إنشاء الخزينة');
                $this->ownerEntry($register, $opening, true, 'رصيد افتتاحي لخزينة ' . $register->name);
            }
        });

        return redirect()->route('cash-registers.index')->with('success', 'تم إنشاء الخزينة.');
    }

    /** تعديل الاسم والحالة فقط. الرصيد لا يُعدَّل من هنا أبداً */
    public function update(Request $request, CashRegister $cashRegister)
    {
        $validated = $request->validate([
            'name'   => 'required|string|max:255',
            'status' => 'required|in:active,inactive',
        ]);

        if ($validated['status'] === 'inactive' && $cashRegister->openShift()->exists()) {
            throw ValidationException::withMessages(['status' => 'على الخزينة وردية مفتوحة. أغلقها أولاً ثم أوقف الخزينة.']);
        }

        $cashRegister->update($validated);

        return back()->with('success', 'تم حفظ بيانات الخزينة.');
    }

    public function destroy(CashRegister $cashRegister)
    {
        if (abs((float) $cashRegister->current_balance) >= 0.01) {
            return back()->with('error', 'لا يمكن حذف خزينة فيها رصيد (' . number_format((float) $cashRegister->current_balance, 2) . ' ₪). حوّل رصيدها لخزينة أخرى أولاً.');
        }
        if ($cashRegister->openShift()->exists()) {
            return back()->with('error', 'على الخزينة وردية مفتوحة. أغلقها أولاً.');
        }

        $cashRegister->delete();

        return redirect()->route('cash-registers.index')->with('success', 'تم حذف الخزينة (يمكن استرجاعها من سلة المحذوفات).');
    }

    /** إيداع يدوي: نقد يدخل الخزينة من خارج المبيعات (فكة من المالك مثلاً) */
    public function deposit(Request $request, CashRegister $cashRegister)
    {
        $validated = $this->validateManual($request);

        DB::transaction(function () use ($cashRegister, $validated) {
            CashBook::in($cashRegister, (float) $validated['amount'], 'deposit', $validated['reason']);
            $this->ownerEntry($cashRegister, (float) $validated['amount'], true, 'إيداع في ' . $cashRegister->name . ': ' . $validated['reason']);
        });

        return back()->with('success', 'تم تسجيل الإيداع.');
    }

    /** سحب يدوي: نقد يخرج من الخزينة لغير المصروفات والموردين (المالك أخذ مبلغاً مثلاً) */
    public function withdraw(Request $request, CashRegister $cashRegister)
    {
        $validated = $this->validateManual($request);

        DB::transaction(function () use ($cashRegister, $validated) {
            CashBook::out($cashRegister, (float) $validated['amount'], 'withdrawal', $validated['reason']);
            $this->ownerEntry($cashRegister, (float) $validated['amount'], false, 'سحب من ' . $cashRegister->name . ': ' . $validated['reason']);
        });

        return back()->with('success', 'تم تسجيل السحب.');
    }

    /** تحويل بين خزينتين في نفس الصيدلية (مثلاً من درج الكاشير للخزنة الرئيسية) */
    public function transfer(Request $request)
    {
        $validated = $request->validate([
            'from_register_id' => 'required|integer',
            'to_register_id'   => 'required|integer|different:from_register_id',
            'amount'           => 'required|numeric|min:0.01',
            'note'             => 'nullable|string|max:255',
        ], ['to_register_id.different' => 'اختر خزينة مختلفة عن المصدر.']);

        // الاستعلام عبر الموديل يضمن أن الخزينتين ضمن صيدلية/فرع المستخدم
        $from = CashRegister::where('status', 'active')->findOrFail($validated['from_register_id']);
        $to = CashRegister::where('status', 'active')->findOrFail($validated['to_register_id']);

        $orgOf = fn ($r) => (int) Branch::withoutGlobalScope('tenant')->whereKey($r->branch_id)->value('organization_id');
        if ($orgOf($from) !== $orgOf($to)) {
            throw ValidationException::withMessages(['to_register_id' => 'لا يمكن التحويل لخزينة في صيدلية أخرى.']);
        }

        CashBook::transfer($from, $to, (float) $validated['amount'], $validated['note'] ?? null);

        return back()->with('success', 'تم تحويل ' . number_format((float) $validated['amount'], 2) . ' ₪ من «' . $from->name . '» إلى «' . $to->name . '».');
    }

    private function validateManual(Request $request): array
    {
        return $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:255',
        ], ['reason.required' => 'اكتب سبب الحركة حتى تظهر في كشف الخزينة.']);
    }

    /** قيد الإيداع/السحب: بين الصندوق وجاري المالك (لا يؤثر على الأرباح) */
    private function ownerEntry(CashRegister $register, float $amount, bool $deposit, string $description): void
    {
        $orgId = (int) Branch::withoutGlobalScope('tenant')->whereKey($register->branch_id)->value('organization_id');
        if (!$orgId) {
            return;
        }

        $cash = AccountingService::systemAccount(AccountingService::CASH, $orgId)->id;
        $owner = AccountingService::systemAccount(AccountingService::OWNER, $orgId)->id;

        AccountingService::post($orgId, $description, $deposit
            ? [['account_id' => $cash, 'debit' => $amount, 'credit' => 0], ['account_id' => $owner, 'debit' => 0, 'credit' => $amount]]
            : [['account_id' => $owner, 'debit' => $amount, 'credit' => 0], ['account_id' => $cash, 'debit' => 0, 'credit' => $amount]],
            AccountingService::nextEntryNumber($deposit ? 'CD' : 'CW'), null, 'cash_register', $register->id);
    }
}
