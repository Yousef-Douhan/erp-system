<?php
namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'total'          => 'nullable|numeric|min:0',
        ]);

        $validated['organization_id'] = $this->currentOrganizationId();
        $validated['employee_id'] = auth()->id();
        $validated['total'] = round((float) ($validated['total'] ?? 0), 2);
        // المدفوع يُسجَّل فقط عبر «دفعة للمورد» حتى يمر على الخزينة وكشف المورد والقيود
        $validated['paid_amount'] = 0;
        $validated['status'] = $validated['total'] > 0 ? 'unpaid' : 'paid';

        $purchase = DB::transaction(function () use ($validated) {
            $purchase = Purchase::create($validated);
            \App\Services\SupplierLedger::syncPurchase($purchase, 0);

            return $purchase;
        });

        return redirect()->route('purchases.index', ['show' => $purchase->id])->with('success', 'تم إنشاء فاتورة الشراء. أضف الأصناف لتدخل المخزون.');
    }

    // تحديث فاتورة موجودة (Update)
    public function update(Request $request, Purchase $purchase)
    {
        $this->authorize('update', $purchase);

        $validated = $request->validate([
            'branch_id'      => 'required|exists:branches,id,deleted_at,NULL',
            'supplier_id'    => 'required|exists:suppliers,id,deleted_at,NULL',
            'invoice_number' => ['required', 'string', \Illuminate\Validation\Rule::unique('purchases', 'invoice_number')->where('organization_id', $purchase->organization_id)->ignore($purchase->id)],
            'total'          => 'nullable|numeric|min:0',
        ]);

        $oldTotal = (float) $purchase->total;
        $oldSupplier = (int) $purchase->supplier_id;
        $supplierChanged = (int) $validated['supplier_id'] !== $oldSupplier;

        if ($supplierChanged && $purchase->payments()->exists()) {
            return back()->with('error', 'لا يمكن تغيير مورد فاتورة سُجلت عليها دفعات.');
        }

        // فاتورة لها أصناف: إجماليها = مجموع الأصناف ولا يُكتب يدوياً
        $validated['total'] = $purchase->items()->exists()
            ? (float) $purchase->items()->sum('total')
            : round((float) ($validated['total'] ?? $oldTotal), 2);
        $returned = (float) $purchase->returned_amount;
        $validated['status'] = \App\Services\SaleService::statusFor(max(0, $validated['total'] - $returned), (float) $purchase->paid_amount);

        if ($validated['total'] + 0.001 < (float) $purchase->paid_amount + $returned) {
            return back()->with('error', 'الإجمالي لا يمكن أن يكون أقل من المدفوع للمورد والمرتجع له (' . number_format((float) $purchase->paid_amount + $returned, 2) . ').');
        }
        if ($supplierChanged && $purchase->returns()->exists()) {
            return back()->with('error', 'لا يمكن تغيير مورد فاتورة عليها مرتجعات.');
        }

        DB::transaction(function () use ($purchase, $validated, $oldTotal, $oldSupplier, $supplierChanged) {
            $purchase->update($validated);

            if ($supplierChanged) {
                \App\Services\SupplierLedger::syncPurchase($purchase, $oldTotal, 0, $oldSupplier);
                \App\Services\SupplierLedger::syncPurchase($purchase, 0);
            } else {
                \App\Services\SupplierLedger::syncPurchase($purchase, $oldTotal);
            }
        });

        return redirect()->route('purchases.index')->with('success', 'تم تحديث فاتورة الشراء بنجاح.');
    }

    /**
     * دفعة للمورد على فاتورة شراء: تُسحب من الخزينة (أو البنك)، تنقص المتبقي ورصيد المورد،
     * وقيد: من حـ/ ذمم الموردين  إلى حـ/ الصندوق أو البنك
     */
    public function pay(Request $request, Purchase $purchase)
    {
        $this->authorize('pay', $purchase);

        $remaining = $purchase->remaining();
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

        // التحقق من رصيد الخزينة والتسجيل في الفاتورة والخزينة وكشف المورد والقيد: كله في الخدمة
        $register = $validated['payment_method'] === 'cash'
            ? \App\Services\CashRegisterService::resolve((int) $validated['cash_register_id'], $request->user(), $purchase->branch_id, (float) $validated['amount'])
            : null;
        \App\Services\SupplierPaymentService::payInvoice($purchase, (float) $validated['amount'], $validated['payment_method'], $register, $request->user(), $validated['reference'] ?? null, $validated['notes'] ?? null);

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
        if ($purchase->returns()->exists()) {
            return back()->with('error', 'لا يمكن حذف فاتورة عليها مرتجعات للمورد.');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($purchase) {
            // سحب كميات الفاتورة من المخزون (يرفض إذا كانت قد بيعت)
            foreach ($purchase->items as $item) {
                \App\Support\Stock::move((int) $item->product_id, (int) $purchase->branch_id, -(int) $item->quantity, 'purchase_reversed', 'purchase_item', $item->id);
            }

            \App\Services\SupplierLedger::syncPurchase($purchase, (float) $purchase->total, 0);

            $purchase->items()->delete();
            $purchase->delete();
        });
        return redirect()->route('purchases.index')->with('success', 'تم حذف الفاتورة بنجاح.');
    }
}
