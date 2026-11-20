<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use App\Models\TransferCheck;
use App\Models\TransferStatement;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * مطابقة الحوالات (بنك / محافظ):
 *  - صاحب الصيدلية يرفع كشف الحساب وصور الحوالات (upload_transfer_docs)
 *  - موظف المطابقة يرى المستندات ويؤكد كل حوالة: وصلت (بتاريخ وصولها ومن أي مستند) أو لم تصل (verify_transfers)
 *  - الحوالة قد تصل في يوم لاحق: تبقى في «بانتظار المطابقة» حتى تُطابق، وكل مطابقة يمكن تعديلها لاحقاً
 */
class TransferCheckController extends Controller
{
    private const FILE_RULE = 'file|mimes:jpg,jpeg,png,webp,pdf|max:5120';

    public function index(Request $request)
    {
        $user = $request->user();
        $view = $request->query('view') === 'day' ? 'day' : 'pending';
        $date = $request->filled('date') ? Carbon::parse($request->date) : today();
        $method = $request->query('method');
        $status = in_array($request->query('status'), ['confirmed', 'rejected', 'pending'], true) ? $request->query('status') : null;

        $q = \App\Services\TransferCheckService::clean((string) $request->query('q'));
        $search = fn ($query) => $q ? $this->applySearch($query, $q) : $query;

        $with = ['sale:id,invoice_number', 'customer:id,name', 'user:id,username', 'checker:id,username', 'statement:id,original_name,file_path'];

        if ($view === 'pending') {
            // كل ما لم يُطابق بعد، من كل الأيام (الأقدم أولاً)
            $checks = $search(TransferCheck::where('status', 'pending'))
                ->when($method, fn ($x) => $x->where('payment_method', $method))
                ->with($with)->orderBy('created_at')->get();
        } else {
            $checks = $search(TransferCheck::whereDate('created_at', $date))
                ->when($method, fn ($x) => $x->where('payment_method', $method))
                ->when($status, fn ($x) => $x->where('status', $status))
                ->with($with)
                ->orderByRaw("FIELD(status, 'pending', 'rejected', 'confirmed')")->orderBy('created_at')->get();
        }

        // ملخص لكل طريقة دفع (اليوم المختار، أو كل المنتظر)
        $base = $view === 'pending' ? TransferCheck::where('status', 'pending') : TransferCheck::whereDate('created_at', $date);
        $byMethod = $base->selectRaw("payment_method,
                SUM(CASE WHEN direction = 'in' THEN amount ELSE 0 END) AS total_in,
                SUM(CASE WHEN direction = 'out' THEN amount ELSE 0 END) AS total_out,
                SUM(CASE WHEN direction = 'in' AND status = 'confirmed' THEN amount ELSE 0 END) AS confirmed,
                SUM(CASE WHEN direction = 'in' AND status = 'pending' THEN amount ELSE 0 END) AS pending,
                SUM(CASE WHEN direction = 'in' AND status = 'rejected' THEN amount ELSE 0 END) AS rejected,
                COUNT(*) AS cnt")
            ->groupBy('payment_method')->get();

        $pendingCount = TransferCheck::where('status', 'pending')->count();

        // المستندات: في عرض المنتظر آخر 14 يوماً، وفي عرض اليوم مستندات ذلك اليوم
        $documents = TransferStatement::with('user:id,username')->withCount('checks')
            ->when($view === 'pending', fn ($q) => $q->whereDate('statement_date', '>=', today()->subDays(14)))
            ->when($view === 'day', fn ($q) => $q->whereDate('statement_date', $date))
            ->orderByDesc('statement_date')->orderByDesc('id')->get();

        // للاختيار عند التأكيد: مستندات آخر 30 يوماً
        $docOptions = TransferStatement::whereDate('statement_date', '>=', today()->subDays(30))
            ->orderByDesc('statement_date')->orderByDesc('id')->get(['id', 'statement_date', 'payment_method', 'original_name', 'note']);

        $methods = PaymentMethod::pluck('name', 'code')->all();
        $canVerify = $user->can('verify_transfers');
        $canUpload = $user->can('upload_transfer_docs');

        return view('transfers.index', compact(
            'view', 'q', 'checks', 'byMethod', 'pendingCount', 'documents', 'docOptions', 'methods', 'date', 'status', 'method', 'canVerify', 'canUpload'
        ));
    }

    // ===================== المطابقة (verify_transfers) =====================

    /** وصلت: بتاريخ وصولها (افتراضياً اليوم) ومن أي مستند */
    public function confirm(Request $request, TransferCheck $transferCheck)
    {
        $data = $this->validateMatch($request, $transferCheck);
        $transferCheck->update($data + ['status' => 'confirmed', 'checked_by' => $request->user()->id, 'checked_at' => now()]);

        $late = $data['received_on'] !== $transferCheck->created_at->toDateString();

        return back()->with('success', 'تم تأكيد وصول ' . number_format((float) $transferCheck->amount, 2) . ' ₪' . ($late ? ' (وصلت بتاريخ ' . $data['received_on'] . ')' : '') . '.');
    }

    /** لم تصل: لازم سبب. تبقى قابلة للتعديل إذا وصلت لاحقاً */
    public function reject(Request $request, TransferCheck $transferCheck)
    {
        $request->validate(['note' => 'required|string|max:500'], ['note.required' => 'اكتب ملاحظة: ماذا حدث بالحوالة؟']);

        $transferCheck->update([
            'status' => 'rejected', 'note' => $request->note, 'received_on' => null, 'statement_id' => null,
            'checked_by' => $request->user()->id, 'checked_at' => now(),
        ]);

        return back()->with('success', 'سُجّلت الحوالة «لم تصل». إذا وصلت لاحقاً عدّلها إلى «وصلت».');
    }

    /** تعديل مطابقة سابقة: الحالة، تاريخ الوصول، المستند، الملاحظة */
    public function update(Request $request, TransferCheck $transferCheck)
    {
        $request->validate(['status' => 'required|in:pending,confirmed,rejected']);
        $status = $request->status;

        if ($status === 'pending') {
            $transferCheck->update(['status' => 'pending', 'checked_by' => null, 'checked_at' => null, 'received_on' => null, 'statement_id' => null]);

            return back()->with('success', 'أُعيدت الحوالة إلى «بانتظار المطابقة».');
        }

        if ($status === 'rejected') {
            return $this->reject($request, $transferCheck);
        }

        return $this->confirm($request, $transferCheck);
    }

    /** إرجاع سريع إلى «بانتظار المطابقة» */
    public function reopen(Request $request, TransferCheck $transferCheck)
    {
        $request->merge(['status' => 'pending']);

        return $this->update($request, $transferCheck);
    }

    /** تأكيد عدة حوالات دفعة واحدة */
    public function bulk(Request $request)
    {
        $validated = $request->validate([
            'ids'          => 'required|array|min:1',
            'ids.*'        => 'integer',
            'received_on'  => 'nullable|date|before_or_equal:today',
            'statement_id' => ['nullable', 'integer', $this->documentExists()],
        ], ['ids.required' => 'اختر حوالة واحدة على الأقل.', 'received_on.before_or_equal' => 'تاريخ الوصول لا يمكن أن يكون في المستقبل.']);

        // النطاق التلقائي يمنع لمس حوالات صيدلية/فرع آخر
        $checks = TransferCheck::whereIn('id', $validated['ids'])->where('status', 'pending')->get();
        if ($checks->isEmpty()) {
            return back()->with('error', 'لا توجد حوالات بانتظار المطابقة ضمن المحدد.');
        }

        $receivedOn = $validated['received_on'] ?? today()->toDateString();
        if ($checks->contains(fn ($c) => $c->created_at->toDateString() > $receivedOn)) {
            return back()->with('error', 'تاريخ الوصول قبل تاريخ بعض الحوالات المحددة.');
        }

        TransferCheck::whereIn('id', $checks->pluck('id'))->update([
            'status' => 'confirmed', 'checked_by' => $request->user()->id, 'checked_at' => now(),
            'received_on' => $receivedOn, 'statement_id' => $validated['statement_id'] ?? null, 'updated_at' => now(),
        ]);

        return back()->with('success', 'تم تأكيد ' . $checks->count() . ' حوالة بمجموع ' . number_format((float) $checks->sum('amount'), 2) . ' ₪.');
    }

    // ===================== المستندات (upload_transfer_docs) =====================

    /** رفع كشف حساب أو صور حوالات (عدة ملفات مرة واحدة) */
    public function storeDocuments(Request $request)
    {
        $validated = $request->validate([
            'files'          => 'required|array|min:1|max:20',
            'files.*'        => self::FILE_RULE,
            'statement_date' => 'required|date|before_or_equal:today',
            'payment_method' => 'nullable|string|max:50',
            'note'           => 'nullable|string|max:500',
        ], [
            'files.required'  => 'اختر ملفاً واحداً على الأقل.',
            'files.max'       => 'الحد الأقصى 20 ملفاً في المرة.',
            'files.*.mimes'   => 'الملفات يجب أن تكون صوراً (JPG/PNG/WEBP) أو PDF.',
            'files.*.max'     => 'حجم الملف الواحد أكبر من 5 ميغابايت.',
        ]);

        $org = \App\Support\Tenancy::orgId() ?? $request->user()->organization_id;
        foreach ($request->file('files') as $file) {
            TransferStatement::create([
                'organization_id' => $org,
                'branch_id'       => \App\Support\Tenancy::branchId(),
                'user_id'         => $request->user()->id,
                'payment_method'  => $validated['payment_method'] ?? null,
                'statement_date'  => $validated['statement_date'],
                'file_path'       => $file->store('transfers/' . ($org ?: 'admin') . '/documents/' . now()->format('Y-m'), 'local'),
                'original_name'   => mb_substr($file->getClientOriginalName(), 0, 250),
                'note'            => $validated['note'] ?? null,
            ]);
        }

        return back()->with('success', 'تم رفع ' . count($request->file('files')) . ' مستند. موظف المطابقة يراها الآن.');
    }

    /** حذف مستند رُفع بالخطأ (فقط إذا لم تُربط به أي حوالة) */
    public function destroyDocument(TransferStatement $transferStatement)
    {
        if ($transferStatement->checks()->exists()) {
            return back()->with('error', 'لا يمكن حذف مستند مُطابَقة عليه حوالات. عدّل تلك الحوالات أولاً.');
        }

        Storage::disk('local')->delete($transferStatement->file_path);
        $transferStatement->delete();

        return back()->with('success', 'تم حذف المستند.');
    }

    // ===================== عرض الملفات =====================

    public function proof(TransferCheck $transferCheck)
    {
        abort_unless($transferCheck->proof_path && Storage::disk('local')->exists($transferCheck->proof_path), 404);

        return response()->file(Storage::disk('local')->path($transferCheck->proof_path));
    }

    public function statementFile(TransferStatement $transferStatement)
    {
        abort_unless(Storage::disk('local')->exists($transferStatement->file_path), 404);

        return response()->file(Storage::disk('local')->path($transferStatement->file_path), [
            'Content-Disposition' => 'inline; filename="document-' . $transferStatement->id . '.' . pathinfo($transferStatement->file_path, PATHINFO_EXTENSION) . '"',
        ]);
    }

    // ===================== مساعدات =====================

    /**
     * البحث بما يظهر في كشف الحساب: اسم المحوِّل، رقم الجوال/المحفظة، رقم الحساب، رقم العملية، المبلغ، أو رقم الفاتورة.
     * الأرقام تُطابق بآخر 9 خانات حتى لا يهم 059 أو +970 أو المسافات.
     */
    private function applySearch($query, string $q)
    {
        $digits = preg_replace('/\D/', '', $q);
        $tail = strlen($digits) >= 6 ? substr($digits, -9) : null;
        $amount = is_numeric(str_replace(',', '', $q)) ? round((float) str_replace(',', '', $q), 2) : null;

        return $query->where(function ($w) use ($q, $tail, $amount) {
            $w->where('payer_name', 'like', "%{$q}%")
                ->orWhere('transfer_ref', 'like', "%{$q}%")
                ->orWhere('wallet_phone', 'like', "%{$q}%")
                ->orWhere('payer_account', 'like', "%{$q}%")
                ->orWhereHas('sale', fn ($s) => $s->where('invoice_number', 'like', "%{$q}%"))
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$q}%"));

            if ($tail) {
                $w->orWhereRaw("REPLACE(REPLACE(REPLACE(wallet_phone, ' ', ''), '-', ''), '+', '') LIKE ?", ["%{$tail}%"])
                    ->orWhereRaw("REPLACE(REPLACE(payer_account, ' ', ''), '-', '') LIKE ?", ["%{$tail}%"]);
            }
            if ($amount !== null && $amount > 0) {
                $w->orWhere('amount', $amount);
            }
        });
    }

    private function validateMatch(Request $request, TransferCheck $check): array
    {
        $validated = $request->validate([
            'received_on'  => ['nullable', 'date', 'before_or_equal:today', 'after_or_equal:' . $check->created_at->toDateString()],
            'statement_id' => ['nullable', 'integer', $this->documentExists()],
            'note'         => 'nullable|string|max:500',
        ], [
            'received_on.before_or_equal' => 'تاريخ الوصول لا يمكن أن يكون في المستقبل.',
            'received_on.after_or_equal'  => 'تاريخ الوصول لا يمكن أن يكون قبل تاريخ الحوالة.',
        ]);

        $data = [
            'received_on'  => $validated['received_on'] ?? today()->toDateString(),
            'statement_id' => $validated['statement_id'] ?? null,
        ];
        if (array_key_exists('note', $validated)) {
            $data['note'] = $validated['note'];
        }

        return $data;
    }

    /** المستند يجب أن يكون من صيدلية المستخدم (النطاق التلقائي) */
    private function documentExists()
    {
        return function ($attribute, $value, $fail) {
            if ($value && !TransferStatement::whereKey($value)->exists()) {
                $fail('المستند المختار غير موجود.');
            }
        };
    }
}
