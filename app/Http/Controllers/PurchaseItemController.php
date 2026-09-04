<?php

namespace App\Http\Controllers;

use App\Models\PurchaseItem;
use App\Models\Purchase;
use Illuminate\Http\Request;

class PurchaseItemController extends Controller
{
    // تخزين عنصر جديد تابع لفاتورة شراء
    public function store(Request $request, Purchase $purchase)
    {
        $validated = $request->validate([
            'product_id'    => 'required|exists:products,id',
            'batch_number'  => 'nullable|string|max:255',
            'expiry_date'   => 'nullable|date',
            'quantity'      => 'required|integer|min:1',
            'unit_cost'     => 'required|numeric|min:0',
        ]);

        // حساب الإجمالي تلقائياً
        $validated['total'] = $validated['quantity'] * $validated['unit_cost'];
        $validated['purchase_id'] = $purchase->id;

        PurchaseItem::create($validated);

        // تحديث الإجمالي الكلي لفاتورة الشراء إذا رغبت
        $this->updatePurchaseTotal($purchase);

        return redirect()->back()->with('success', 'تم إضافة المنتج إلى الفاتورة بنجاح.');
    }

    // تحديث عنصر موجود
    public function update(Request $request, PurchaseItem $purchaseItem)
    {
        $validated = $request->validate([
            'product_id'    => 'required|exists:products,id',
            'batch_number'  => 'nullable|string|max:255',
            'expiry_date'   => 'nullable|date',
            'quantity'      => 'required|integer|min:1',
            'unit_cost'     => 'required|numeric|min:0',
        ]);

        $validated['total'] = $validated['quantity'] * $validated['unit_cost'];

        $purchaseItem->update($validated);

        // تحديث الإجمالي الكلي للفاتورة
        $this->updatePurchaseTotal($purchaseItem->purchase);

        return redirect()->back()->with('success', 'تم تحديث العنصر بنجاح.');
    }

    // حذف عنصر من الفاتورة
    public function destroy(PurchaseItem $purchaseItem)
    {
        $purchase = $purchaseItem->purchase;
        $purchaseItem->delete();

        // تحديث الإجمالي الكلي للفاتورة
        $this->updatePurchaseTotal($purchase);

        return redirect()->back()->with('success', 'تم حذف العنصر من الفاتورة.');
    }

    // دالة مساعدة لتحديث إجمالي الفاتورة الرئيسية تلقائياً
    private function updatePurchaseTotal(Purchase $purchase)
    {
        $total = $purchase->items()->sum('total');
        $purchase->update(['total' => $total]);
    }
}
