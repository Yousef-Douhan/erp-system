<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Sale;
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

        $customers = Customer::visibleTo($request->user())->orderBy('name')->get(['id', 'name']);

        return view('sales.index', compact('sales', 'customers'));
    }

    /**
     * تعديل بيانات الفاتورة الوصفية فقط: ربطها بعميل، اسم العميل، رقم جوال المحفظة.
     * المبالغ والأصناف لا تُعدّل من هنا أبداً — أي تغيير عليها يكون بمرتجع أو بتسجيل دفعة،
     * حتى تبقى الخزينة والمخزون والقيود مطابقة للفاتورة.
     */
    public function update(Request $request, Sale $sale)
    {
        $this->authorize('update', $sale);

        $validated = $request->validate([
            'customer_id'   => 'nullable|integer|exists:customers,id,deleted_at,NULL',
            'customer_name' => 'nullable|string|max:255',
            'wallet_phone'  => 'nullable|string|max:30',
        ]);

        $customerId = !empty($validated['customer_id']) ? (int) $validated['customer_id'] : null;
        if ($customerId) {
            // العميل يجب أن يكون من نفس الصيدلية/الفرع (النطاق التلقائي يخفي غيره)
            $customer = Customer::findOrFail($customerId);
            $validated['customer_name'] = $customer->name;
        } elseif (SaleService::remaining($sale) > 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'customer_id' => 'على هذه الفاتورة مبلغ متبقٍ، فلا يمكن فصلها عن العميل.',
            ]);
        }

        DB::transaction(function () use ($sale, $validated, $customerId) {
            $oldCustomerId = $sale->customer_id ? (int) $sale->customer_id : null;
            $oldRemaining = SaleService::remaining($sale);

            $sale->update([
                'customer_id'   => $customerId,
                'customer_name' => $validated['customer_name'] ?? null,
                'wallet_phone'  => $validated['wallet_phone'] ?? null,
            ]);
            SaleService::syncDebt($sale, $oldCustomerId, $oldRemaining);
        });

        return redirect()->route('sales.index')
            ->with('success', 'تم تحديث بيانات الفاتورة.');
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
                    if ((float) $register->current_balance < (float) $sale->paid_amount) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'sale' => "لا يمكن حذف الفاتورة: يجب سحب " . number_format((float) $sale->paid_amount, 2)
                                . " من خزينة «{$register->name}» ورصيدها " . number_format((float) $register->current_balance, 2) . " فقط. أودِع الفرق أو حوّله للخزينة أولاً.",
                        ]);
                    }
                    \App\Services\CashBook::out($register, (float) $sale->paid_amount, 'sale_cancel', "حذف فاتورة {$sale->invoice_number}", 'sale', $sale->id);
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
        $paymentMethodNames = \App\Models\PaymentMethod::pluck('name', 'code')->all();
        $payMethods = \App\Models\PaymentMethod::where('is_active', true)->get(['code', 'name', 'kind', 'requires_wallet']);

        return view('sales.show', compact('sale', 'paymentMethodNames', 'payMethods'));
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
            'wallet_phone'   => 'nullable|string|max:30',
            'payer_name'     => 'nullable|string|max:120',
            'payer_account'  => 'nullable|string|max:60',
            'transfer_ref'   => 'nullable|string|max:60',
        ]);

        $payMethod = \App\Models\PaymentMethod::where('code', $validated['payment_method'] ?? 'cash')->first();
        if ($payMethod && ($payerError = $payMethod->payerError($validated['payer_name'] ?? null, $validated['wallet_phone'] ?? null, (bool) $sale->customer_id))) {
            throw \Illuminate\Validation\ValidationException::withMessages(['wallet_phone' => $payerError]);
        }

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
                    \App\Services\CashBook::in($register, $amount, 'sale_payment', "تحصيل على فاتورة {$sale->invoice_number}", 'sale', $sale->id);
                    $sale->cash_register_id = $sale->cash_register_id ?: $register->id;
                }
            }

            $sale->paid_amount = round((float) $sale->paid_amount + $amount, 2);
            $sale->status = SaleService::statusFor(SaleService::netTotal($sale), (float) $sale->paid_amount);
            $sale->save();

            if ($sale->customer_id) {
                SaleService::reduceDebt($sale->customer_id, $amount, "دفعة على فاتورة رقم {$sale->invoice_number}", $isCash && $sale->cash_register_id ? 'payment' : 'payment_noncash');
            }

            $orgId = $sale->organization_id;
            AccountingService::post($orgId, "دفعة على فاتورة رقم {$sale->invoice_number}", [
                ['account_id' => AccountingService::systemAccount($isCash ? AccountingService::CASH : AccountingService::BANK, $orgId)->id, 'debit' => $amount, 'credit' => 0],
                ['account_id' => AccountingService::systemAccount(AccountingService::RECEIVABLES, $orgId)->id, 'debit' => 0, 'credit' => $amount],
            ], AccountingService::nextEntryNumber('SP'), null, 'sale_payment', $sale->id);

            if (!$isCash) {
                \App\Services\TransferCheckService::record($sale, $amount, $code, 'sale_payment', 'in', [
                    'wallet_phone'  => $validated['wallet_phone'] ?? null,
                    'payer_name'    => ($validated['payer_name'] ?? null) ?: $sale->customer?->name,
                    'payer_account' => $validated['payer_account'] ?? null,
                    'transfer_ref'  => $validated['transfer_ref'] ?? null,
                ]);
            }
        });

        return back()->with('success', 'تم تسجيل دفعة بقيمة ' . number_format($validated['amount'], 2) . ' بنجاح.');
    }
}
