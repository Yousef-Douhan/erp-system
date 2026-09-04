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
        $purchases = Purchase::with(['supplier', 'branch', 'employee', 'items'])->latest()->paginate(10);
        $suppliers = Supplier::all();
        $branches = Branch::all();

        // إذا كان الطلب يتطلب التعديل أو العرض سنقوم تمرير المتغيرات المطلوبة
        $editPurchase = $request->has('edit') ? Purchase::with('items')->find($request->edit) : null;
        $showPurchase = $request->has('show') ? Purchase::with(['supplier', 'branch', 'employee', 'items'])->find($request->show) : null;

        return view('purchases.index', compact('purchases', 'suppliers', 'branches', 'editPurchase', 'showPurchase'));
    }

    // حفظ فاتورة جديدة (Store)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id'      => 'required|exists:branches,id',
            'supplier_id'    => 'required|exists:suppliers,id',
            'invoice_number' => 'required|string|unique:purchases,invoice_number',
            'total'          => 'required|numeric|min:0',
            'paid_amount'    => 'nullable|numeric|min:0',
        ]);

        // جلب معرف المنظمة تلقائياً من المستخدم الحالي، أو تعيين القيمة 1 كافتراضي إذا لم تكن موجودة
        $validated['organization_id'] = auth()->user()->organization_id ?? 1;
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
        $validated = $request->validate([
            'branch_id'      => 'required|exists:branches,id',
            'supplier_id'    => 'required|exists:suppliers,id',
            'invoice_number' => 'required|string|unique:purchases,invoice_number,' . $purchase->id,
            'total'          => 'required|numeric|min:0',
            'paid_amount'    => 'nullable|numeric|min:0',
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

    // حذف الفاتورة (Destroy)
    public function destroy(Purchase $purchase)
    {
        $purchase->delete();
        return redirect()->route('purchases.index')->with('success', 'تم حذف الفاتورة بنجاح.');
    }
}
