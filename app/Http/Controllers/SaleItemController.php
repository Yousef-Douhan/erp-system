<?php

namespace App\Http\Controllers;

use App\Models\SaleItem;
use App\Models\Sale;
use Illuminate\Http\Request;

class SaleItemController extends Controller
{
    // تخزين عنصر جديد وتحديث إجمالي الفاتورة تلقائياً
    public function store(Request $request, Sale $sale)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'batch_id'   => 'nullable|integer',
            'quantity'   => 'required|numeric|min:0.01',
            'unit_price' => 'required|numeric|min:0',
            'discount'   => 'nullable|numeric|min:0',
        ]);

        // حساب الإجمالي للعنصر: (الكمية × السعر) - الخصم
        $quantity = $validated['quantity'];
        $unitPrice = $validated['unit_price'];
        $discount = $validated['discount'] ?? 0;

        $validated['sale_id'] = $sale->id;
        $validated['total'] = ($quantity * $unitPrice) - $discount;

        SaleItem::create($validated);

        // تحديث إجمالي الفاتورة الكلي بناءً على مجموع عناصرها
        $newTotal = $sale->items()->sum('total');
        $sale->update([
            'total' => $newTotal,
            // تحديث الحالة إذا لزم الأمر أو تركها للمستخدم
        ]);

        return back()->with('success', 'تم إضافة المنتج إلى الفاتورة بنجاح.');
    }

    // تحديث عنصر موجود في الفاتورة
    public function update(Request $request, SaleItem $saleItem)
    {
        $validated = $request->validate([
            'quantity'   => 'required|numeric|min:0.01',
            'unit_price' => 'required|numeric|min:0',
            'discount'   => 'nullable|numeric|min:0',
        ]);

        $quantity = $validated['quantity'];
        $unitPrice = $validated['unit_price'];
        $discount = $validated['discount'] ?? 0;

        $validated['total'] = ($quantity * $unitPrice) - $discount;

        $saleItem->update($validated);

        // تحديث إجمالي الفاتورة المرتبطة
        $sale = $saleItem->sale;
        $sale->update([
            'total' => $sale->items()->sum('total')
        ]);

        return back()->with('success', 'تم تحديث عنصر الفاتورة بنجاح.');
    }

    // حذف عنصر من الفاتورة
    public function destroy(SaleItem $saleItem)
    {
        $sale = $saleItem->sale;
        $saleItem->delete();

        // إعادة حساب إجمالي الفاتورة بعد الحذف
        $sale->update([
            'total' => $sale->items()->sum('total')
        ]);

        return back()->with('success', 'تم حذف العنصر من الفاتورة.');
    }


    
}
