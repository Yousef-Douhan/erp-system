<?php

namespace App\Http\Controllers;

use App\Models\CashRegister;
use App\Models\CashShift;
use App\Services\CashRegisterService;
use App\Services\ShiftService;
use Illuminate\Http\Request;

class CashShiftController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $canAll = $user->can('manage_accounting');

        $myShift = CashRegisterService::openShiftFor($user);
        $mySummary = $myShift ? ShiftService::summary($myShift) : null;

        $shifts = CashShift::with(['cashRegister:id,name', 'user:id,username', 'closer:id,username'])
            ->when(!$canAll, fn ($q) => $q->where('user_id', $user->id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest('opened_at')
            ->paginate(20)
            ->withQueryString();

        $busy = CashShift::where('status', 'open')->pluck('cash_register_id');
        $registers = CashRegister::where('status', 'active')->orderBy('name')->get(['id', 'name', 'current_balance'])
            ->map(fn ($r) => tap($r, fn ($r) => $r->busy = $busy->contains($r->id)));

        return view('shifts.index', compact('myShift', 'mySummary', 'shifts', 'registers', 'canAll'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'cash_register_id' => 'required|exists:cash_registers,id,deleted_at,NULL',
            'notes'            => 'nullable|string|max:500',
        ]);

        ShiftService::open(CashRegister::findOrFail($validated['cash_register_id']), $request->user(), $validated['notes'] ?? null);

        return redirect()->route('shifts.index')->with('success', 'تم فتح الوردية. كل المبيعات النقدية ستُسجل على هذه الخزينة.');
    }

    public function show(Request $request, CashShift $shift)
    {
        $this->ensureCanSee($request, $shift);

        $shift->load(['cashRegister', 'user', 'closer']);
        $summary = ShiftService::summary($shift);

        // فواتير الكاشير في هذه الوردية فقط
        $invoices = ShiftService::cashierSales($shift)
            ->with('customer:id,name')
            ->latest()
            ->paginate(30, ['*'], 'page')
            ->withQueryString();

        // حسب طريقة الدفع
        $methodNames = \App\Models\PaymentMethod::pluck('name', 'code')->all() + ['cash' => 'نقدي', 'debt' => 'آجل'];
        $byMethod = ShiftService::cashierSales($shift)
            ->selectRaw('payment_method, COUNT(*) AS cnt, SUM(total - COALESCE(returned_amount, 0)) AS net, SUM(paid_amount) AS paid')
            ->groupBy('payment_method')->orderByDesc('net')->get();

        // الأصناف التي باعها
        $saleIds = ShiftService::cashierSales($shift)->select('id');
        $topProducts = \App\Models\SaleItem::query()
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->whereIn('sale_items.sale_id', $saleIds)
            ->groupBy('sale_items.product_id', 'products.name')
            ->selectRaw('products.name, SUM((sale_items.quantity - COALESCE(sale_items.returned_quantity, 0)) * COALESCE(sale_items.unit_factor, 1)) AS qty,
                SUM(sale_items.total * (sale_items.quantity - COALESCE(sale_items.returned_quantity, 0)) / NULLIF(sale_items.quantity, 0)) AS total')
            ->havingRaw('SUM(sale_items.quantity - COALESCE(sale_items.returned_quantity, 0)) > 0')
            ->orderByDesc('qty')->limit(10)->get();

        // كل حركات الخزينة خلال الوردية
        $movements = \App\Models\CashMovement::with('user:id,username')
            ->where('cash_shift_id', $shift->id)
            ->orderBy('id')->get();

        return view('shifts.show', compact('shift', 'summary', 'invoices', 'byMethod', 'methodNames', 'topProducts', 'movements'));
    }

    public function close(Request $request, CashShift $shift)
    {
        $this->ensureCanSee($request, $shift);

        $validated = $request->validate([
            'actual_balance' => 'required|numeric|min:0',
            'notes'          => 'nullable|string|max:500',
            'confirm_cash'   => 'nullable|boolean',
        ]);

        // خطأ شائع: الكاشير يعدّ مدفوعات البنك/المحفظة كأنها نقد في الدرج
        if ($shift->isOpen() && !$request->boolean('confirm_cash')) {
            $summary = ShiftService::summary($shift);
            $surplus = round((float) $validated['actual_balance'] - $summary['expected'], 2);
            $noncash = $summary['mine']['noncash'];
            if ($surplus > 0 && $noncash > 0 && $surplus <= $noncash + 0.01) {
                return back()->withInput()->withErrors([
                    'actual_balance' => 'المبلغ المُدخل أكبر من المتوقع بـ ' . number_format($surplus, 2) . ' ₪، وفي الوردية مدفوعات بنك/محفظة بقيمة '
                        . number_format($noncash, 2) . ' ₪. هذه المدفوعات تذهب للحساب البنكي ولا تكون في الدرج — لا تعدّها. '
                        . 'إذا كان المبلغ نقداً فعلاً في الدرج، علّم «أؤكد أن المبلغ نقدي» وأعد الإغلاق.',
                ]);
            }
        }

        $shift = ShiftService::close($shift, (float) $validated['actual_balance'], $request->user(), $validated['notes'] ?? null);

        $diff = (float) $shift->difference;
        $msg = $diff == 0
            ? 'تم إغلاق الوردية. الصندوق مطابق تماماً.'
            : ($diff > 0
                ? 'تم إغلاق الوردية مع زيادة ' . number_format($diff, 2) . ' ₪ سُجلت كإيراد آخر.'
                : 'تم إغلاق الوردية مع عجز ' . number_format(abs($diff), 2) . ' ₪ سُجل كمصروف آخر.');

        return redirect()->route('shifts.show', $shift)->with('success', $msg);
    }

    /**
     * الكاشير يرى ورديته فقط، والمحاسب يرى كل الورديات
     */
    private function ensureCanSee(Request $request, CashShift $shift): void
    {
        abort_unless($shift->user_id === $request->user()->id || $request->user()->can('manage_accounting'), 403);
    }
}
