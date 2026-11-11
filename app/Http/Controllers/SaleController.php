<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\SaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Sale::class);

        $query = Sale::visibleTo($request->user())->with(['organization', 'branch', 'customer', 'employee']);

        // فلترة المبيعات حسب التاريخ (من - إلى)
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // فلترة سريعة لمبيعات اليوم بنقرة زر
        if ($request->input('filter') === 'today') {
            $query->whereDate('created_at', today());
        }

        // تقسيم الصفحات حتى لا تصبح الصفحة بطيئة مع كثرة الفواتير
        $sales = $query->latest()->paginate(25)->withQueryString();

        $organizations = Organization::all();
        $branches = Branch::all();
        $customers = Customer::visibleTo($request->user())->orderBy('name')->get(['id', 'name']);
        // موظفو الصيدلية/الفرع فقط
        $employees = \App\Support\Tenancy::constrain(User::query(), \App\Support\Tenancy::active() ? 'organization_id' : null, 'branch_id')
            ->orderBy('username')->get(['id', 'username', 'email']);

        return view('sales.index', compact('sales', 'organizations', 'branches', 'customers', 'employees'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Sale::class);

        $validated = $this->validateSale($request);

        DB::transaction(function () use ($validated) {
            $sale = Sale::create($validated);
            SaleService::syncDebt($sale, null, 0);
        });

        return redirect()->route('sales.index')
            ->with('success', 'تم تسجيل فاتورة المبيعات بنجاح.');
    }

    public function update(Request $request, Sale $sale)
    {
        $this->authorize('update', $sale);

        $validated = $this->validateSale($request, $sale);

        DB::transaction(function () use ($sale, $validated) {
            $oldCustomerId = $sale->customer_id;
            $oldRemaining = SaleService::remaining($sale);

            $sale->update($validated);
            SaleService::syncDebt($sale, $oldCustomerId, $oldRemaining);
        });

        return redirect()->route('sales.index')
            ->with('success', 'تم تحديث فاتورة المبيعات بنجاح.');
    }

    /**
     * حذف الفاتورة: إرجاع الكميات للمخزون وإلغاء المتبقي من دين العميل
     */
    public function destroy(Sale $sale)
    {
        $this->authorize('delete', $sale);

        if ($sale->returns()->exists()) {
            return back()->with('error', 'لا يمكن حذف فاتورة عليها مرتجعات. سجّل مرتجعاً للأصناف المتبقية بدلاً من الحذف.');
        }

        DB::transaction(function () use ($sale) {
            // إرجاع الكميات للدفعات والمخزون مع حركة "مرتجع مبيعات"
            foreach ($sale->items as $item) {
                \App\Services\StockService::reverseSaleItem($item);
            }

            // سحب المبلغ النقدي الذي دخل الخزينة مع هذه الفاتورة
            if ($sale->cash_register_id && $sale->payment_method !== 'debt' && (float) $sale->paid_amount > 0) {
                $register = \App\Models\CashRegister::whereKey($sale->cash_register_id)->lockForUpdate()->first();
                if ($register) {
                    $register->decrement('current_balance', min((float) $sale->paid_amount, (float) $register->current_balance));
                }
            }

            // الوصفة الطبية تعود "غير مصروفة"
            if ($sale->prescription_id) {
                \App\Models\Prescription::whereKey($sale->prescription_id)->update(['is_filled' => false]);
            }

            if ($sale->customer_id && SaleService::remaining($sale) > 0) {
                SaleService::reduceDebt($sale->customer_id, SaleService::remaining($sale), "حذف فاتورة رقم {$sale->invoice_number}");
            }

            // حذف القيود المحاسبية المرتبطة بالفاتورة ودفعاتها
            \App\Models\JournalEntry::whereIn('ref_type', ['sale', 'sale_payment'])
                ->where('ref_id', $sale->id)
                ->get()
                ->each(function ($entry) {
                    $entry->lines()->delete();
                    $entry->delete();
                });

            $sale->items()->delete();
            $sale->delete();
        });

        return redirect()->route('sales.index')
            ->with('success', 'تم حذف الفاتورة وإرجاع الكميات للمخزون.');
    }

    public function show(Sale $sale)
    {
        $this->authorize('view', $sale);

        $sale->load(['organization', 'branch', 'customer', 'employee', 'items.product', 'items.batch', 'cashRegister', 'prescription', 'returns' => fn ($q) => $q->latest()]);
        $products = Product::where('is_active', true)->orderBy('name')->get();
        $paymentMethodNames = \App\Models\PaymentMethod::pluck('name', 'code')->all();

        return view('sales.show', compact('sale', 'products', 'paymentMethodNames'));
    }

    /**
     * إيصال حراري 80mm للطباعة
     */
    public function receipt(Sale $sale)
    {
        $this->authorize('view', $sale);

        $sale->load(['branch', 'organization', 'customer', 'employee', 'items.product:id,name', 'returns']);

        return view('sales.receipt', compact('sale'));
    }

    /**
     * تسجيل دفعة على الفاتورة: تزيد المدفوع وتنقص دين العميل
     */
    public function pay(Request $request, Sale $sale)
    {
        $this->authorize('pay', $sale);

        $remaining = SaleService::remaining($sale);

        $validated = $request->validate([
            'amount'         => 'required|numeric|min:0.01|max:' . $remaining,
            'payment_method' => 'nullable|string|max:50',
        ]);

        DB::transaction(function () use ($sale, $validated) {
            $amount = round((float) $validated['amount'], 2);

            // نقدي => يدخل الخزينة وحساب الصندوق، غير ذلك => حساب البنك/المحافظ
            $code = $validated['payment_method'] ?? 'cash';
            $method = \App\Models\PaymentMethod::where('code', $code)->first();
            $isCash = $code === 'cash' || ($method && !$method->requires_wallet);

            if ($isCash) {
                $register = \App\Models\CashRegister::where('status', 'active')
                    ->when($sale->cash_register_id, fn ($q) => $q->whereKey($sale->cash_register_id), fn ($q) => $q->where('branch_id', $sale->branch_id))
                    ->lockForUpdate()->first()
                    ?? \App\Models\CashRegister::where('status', 'active')->lockForUpdate()->first();
                if ($register) {
                    $register->increment('current_balance', $amount);
                    $sale->cash_register_id = $sale->cash_register_id ?: $register->id;
                }
            }

            $sale->paid_amount = round((float) $sale->paid_amount + $amount, 2);
            $sale->status = SaleService::statusFor(SaleService::netTotal($sale), (float) $sale->paid_amount);
            $sale->save();

            if ($sale->customer_id) {
                SaleService::reduceDebt($sale->customer_id, $amount, "دفعة على فاتورة رقم {$sale->invoice_number}", 'payment');
            }

            $orgId = $sale->organization_id;
            AccountingService::post($orgId, "دفعة على فاتورة رقم {$sale->invoice_number}", [
                ['account_id' => AccountingService::systemAccount($isCash ? AccountingService::CASH : AccountingService::BANK, $orgId)->id, 'debit' => $amount, 'credit' => 0],
                ['account_id' => AccountingService::systemAccount(AccountingService::RECEIVABLES, $orgId)->id, 'debit' => 0, 'credit' => $amount],
            ], AccountingService::nextEntryNumber('SP'), null, 'sale_payment', $sale->id);
        });

        return back()->with('success', 'تم تسجيل دفعة بقيمة ' . number_format($validated['amount'], 2) . ' بنجاح.');
    }

    private function validateSale(Request $request, ?Sale $sale = null): array
    {
        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id,deleted_at,NULL',
            'branch_id'       => 'required|exists:branches,id,deleted_at,NULL',
            'customer_id'     => 'nullable|exists:customers,id,deleted_at,NULL',
            'customer_name'   => 'nullable|string|max:255',
            'employee_id'     => 'required|exists:users,id,deleted_at,NULL',
            'invoice_number'  => 'required|string|max:255|unique:sales,invoice_number' . ($sale ? ',' . $sale->id : ''),
            'total'           => 'required|numeric|min:0',
            'paid_amount'     => 'required|numeric|min:0|lte:total',
            'payment_method'  => 'nullable|string|max:50',
            'wallet_phone'    => 'nullable|string|max:30',
        ]);

        if (empty($validated['customer_id']) && (float) $validated['paid_amount'] < (float) $validated['total']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'customer_id' => 'الفاتورة غير المدفوعة بالكامل تحتاج إلى عميل مسجل لترحيل الدين عليه.',
            ]);
        }

        if (!empty($validated['customer_id'])) {
            $validated['customer_name'] = Customer::whereKey($validated['customer_id'])->value('name');
        }

        $validated['payment_method'] = $validated['payment_method'] ?? 'cash';
        // الحالة تُحسب تلقائياً من الإجمالي والمدفوع
        $validated['status'] = SaleService::statusFor(
            max(0, (float) $validated['total'] - (float) ($sale->returned_amount ?? 0)),
            (float) $validated['paid_amount']
        );

        return $validated;
    }
}
