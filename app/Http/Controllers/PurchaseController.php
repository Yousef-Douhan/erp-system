<?php
namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Branch;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    // عرض القائمة الرئيسية (والتي تضم نموذج الإضافة والتعديل عبر Modal أو متغيرات)
    public function index(Request $request)
    {
        $this->authorize('viewAny', Purchase::class);

        $purchases = Purchase::visibleTo($request->user())->with(['supplier', 'branch', 'employee', 'items'])->latest()->paginate(10);
        $suppliers = Supplier::all();
        $branches = Branch::all();

        // إذا كان الطلب يتطلب التعديل أو العرض سنقوم تمرير المتغيرات المطلوبة
        $editPurchase = $request->has('edit') ? Purchase::with('items')->find($request->edit) : null;
        $showPurchase = $request->has('show') ? Purchase::with(['supplier', 'branch', 'employee', 'items', 'payments.user:id,username', 'payments.cashRegister:id,name'])->find($request->show) : null;

        // قائمة المنتجات تُحمَّل فقط عند فتح فاتورة لإضافة أصناف لها
        $products = $showPurchase
            ? \App\Models\Product::where('is_active', true)->orderBy('name')->get(['id', 'name', 'purchase_price'])
            : collect();

        $registers = $showPurchase
            ? \App\Models\CashRegister::where('status', 'active')->orderBy('name')->get(['id', 'name', 'current_balance'])
            : collect();

        return view('purchases.index', compact('purchases', 'suppliers', 'branches', 'editPurchase', 'showPurchase', 'products', 'registers'));
    }

    // حفظ فاتورة جديدة (Store)
    public function store(Request $request)
    {
        $this->authorize('create', Purchase::class);

        $validated = $request->validate([
            'branch_id'      => 'required|exists:branches,id,deleted_at,NULL',
            'supplier_id'    => 'required|exists:suppliers,id,deleted_at,NULL',
            'invoice_number' => ['required', 'string', \Illuminate\Validation\Rule::unique('purchases', 'invoice_number')->where('organization_id', $this->currentOrganizationId())],
            'total'          => 'required|numeric|min:0',
            'paid_amount'    => 'nullable|numeric|min:0|lte:total',
        ]);

        // جلب معرف المنظمة تلقائياً من المستخدم الحالي، أو تعيين القيمة 1 كافتراضي إذا لم تكن موجودة
        $validated['organization_id'] = $this->currentOrganizationId();
        $validated['employee_id'] = auth()->id();

        $paidAmount = $validated['paid_amount'] ?? 0;

        if ($paidAmount >= $validated['total']) {
            $validated['status'] = 'paid';
        } elseif ($paidAmount > 0) {
            $validated['status'] = 'partial';
        } else {
            $validated['status'] = 'unpaid';
        }

        Purchase::create($validated);

        return redirect()->route('purchases.index')->with('success', 'تم إنشاء فاتورة الشراء بنجاح.');
    }

    // تحديث فاتورة موجودة (Update)
    public function update(Request $request, Purchase $purchase)
    {
        $this->authorize('update', $purchase);

        $validated = $request->validate([
            'branch_id'      => 'required|exists:branches,id,deleted_at,NULL',
            'supplier_id'    => 'required|exists:suppliers,id,deleted_at,NULL',
            'invoice_number' => ['required', 'string', \Illuminate\Validation\Rule::unique('purchases', 'invoice_number')->where('organization_id', $purchase->organization_id)->ignore($purchase->id)],
            'total'          => 'required|numeric|min:0',
            'paid_amount'    => 'nullable|numeric|min:0|lte:total',
        ]);

        $paidAmount = $validated['paid_amount'] ?? 0;

        if ($paidAmount >= $validated['total']) {
            $validated['status'] = 'paid';
        } elseif ($paidAmount > 0) {
            $validated['status'] = 'partial';
        } else {
            $validated['status'] = 'unpaid';
        }

        $purchase->update($validated);

        return redirect()->route('purchases.index')->with('success', 'تم تحديث فاتورة الشراء بنجاح.');
    }

    /**
     * دفعة للمورد على فاتورة شراء: تُسحب من الخزينة (أو البنك)، تنقص المتبقي ورصيد المورد،
     * وقيد: من حـ/ ذمم الموردين  إلى حـ/ الصندوق أو البنك
     */
    public function pay(Request $request, Purchase $purchase)
    {
        $this->authorize('pay', $purchase);

        $remaining = round(max(0, (float) $purchase->total - (float) $purchase->paid_amount), 2);
        if ($remaining <= 0) {
            return back()->with('error', 'هذه الفاتورة مسددة بالكامل.');
        }

        $validated = $request->validate([
            'amount'           => 'required|numeric|min:0.01|max:' . $remaining,
            'payment_method'   => 'required|in:cash,bank',
            'cash_register_id' => 'nullable|required_if:payment_method,cash|exists:cash_registers,id,deleted_at,NULL',
            'reference'        => 'nullable|string|max:100',
            'notes'            => 'nullable|string|max:500',
        ], [
            'amount.max' => 'المبلغ أكبر من المتبقي على الفاتورة (' . number_format($remaining, 2) . ').',
            'cash_register_id.required_if' => 'اختر الخزينة التي سيُصرف منها المبلغ.',
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($purchase, $validated, $request) {
            $purchase = Purchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            $amount = round((float) $validated['amount'], 2);
            $isCash = $validated['payment_method'] === 'cash';

            $register = null;
            if ($isCash) {
                $register = \App\Services\CashRegisterService::resolve((int) $validated['cash_register_id'], $request->user(), $purchase->branch_id, $amount);
                $register->decrement('current_balance', $amount);
            }

            \App\Models\PurchasePayment::create([
                'purchase_id'      => $purchase->id,
                'supplier_id'      => $purchase->supplier_id,
                'cash_register_id' => $register?->id,
                'user_id'          => $request->user()->id,
                'amount'           => $amount,
                'payment_method'   => $validated['payment_method'],
                'reference'        => $validated['reference'] ?? null,
                'notes'            => $validated['notes'] ?? null,
            ]);

            $purchase->paid_amount = round((float) $purchase->paid_amount + $amount, 2);
            $purchase->status = \App\Services\SaleService::statusFor((float) $purchase->total, (float) $purchase->paid_amount);
            $purchase->save();

            // كشف حساب المورد (نفس منطق سند الصرف)
            $supplier = Supplier::whereKey($purchase->supplier_id)->lockForUpdate()->first();
            if ($supplier) {
                $supplier->balance = round((float) $supplier->balance - $amount, 2);
                $supplier->save();

                \App\Models\SupplierAccount::create([
                    'supplier_id'      => $supplier->id,
                    'transaction_type' => 'purchase_payment',
                    'debit'            => $amount,
                    'credit'           => 0,
                    'balance'          => $supplier->balance,
                ]);
            }

            $orgId = (int) $purchase->organization_id;
            \App\Services\AccountingService::post($orgId, "دفعة للمورد على فاتورة شراء {$purchase->invoice_number}", [
                ['account_id' => \App\Services\AccountingService::systemAccount(\App\Services\AccountingService::PAYABLES, $orgId)->id, 'debit' => $amount, 'credit' => 0],
                ['account_id' => \App\Services\AccountingService::systemAccount($isCash ? \App\Services\AccountingService::CASH : \App\Services\AccountingService::BANK, $orgId)->id, 'debit' => 0, 'credit' => $amount],
            ], \App\Services\AccountingService::nextEntryNumber('PP'), null, 'purchase_payment', $purchase->id);
        });

        return redirect()->route('purchases.index', ['show' => $purchase->id])
            ->with('success', 'تم تسجيل دفعة بقيمة ' . number_format((float) $validated['amount'], 2) . ' ₪ للمورد.');
    }

    // حذف الفاتورة (Destroy)
    public function destroy(Purchase $purchase)
    {
        $this->authorize('delete', $purchase);

        if ($purchase->payments()->exists()) {
            return back()->with('error', 'لا يمكن حذف فاتورة سُجلت عليها دفعات للمورد.');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($purchase) {
            // سحب كميات الفاتورة من المخزون (يرفض إذا كانت قد بيعت)
            foreach ($purchase->items as $item) {
                \App\Support\Stock::move((int) $item->product_id, (int) $purchase->branch_id, -(int) $item->quantity, 'purchase_reversed', 'purchase_item', $item->id);
            }

            $purchase->items()->delete();
            $purchase->delete();
        });
        return redirect()->route('purchases.index')->with('success', 'تم حذف الفاتورة بنجاح.');
    }
}
