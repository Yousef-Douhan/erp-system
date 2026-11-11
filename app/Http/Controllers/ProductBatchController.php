<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class ProductBatchController extends Controller
{
    // 1. عرض جميع الدفعات في النظام (للصفحة المستقلة)
    public function indexAll()
    {
        $batches = ProductBatch::with(['product', 'warehouse'])->latest()->paginate(10);
        $products = Product::where('is_active', true)->orderBy('name')->get();
        $warehouses = Warehouse::all(); // <-- جلب المستودعات للقائمة المنسدلة

        return view('batches.index', compact('batches', 'products', 'warehouses'));
    }

    public function storeAll(Request $request)
    {
        $validated = $request->validate([
            'product_id'         => 'required|exists:products,id,deleted_at,NULL',
            'warehouse_id'       => 'required|exists:warehouses,id,deleted_at,NULL', // <-- مطلوب الآن
            'batch_number'       => 'required|string|max:100',
            'manufacturing_date' => 'nullable|date',
            'expiry_date'        => 'nullable|date|after_or_equal:manufacturing_date',
            'cost_price'         => 'required|numeric|min:0',
            'quantity'           => 'required|integer|min:0',
        ]);

        \App\Support\PriceGuard::check(['cost_price' => [$validated['product_id'], $validated['cost_price']]]);
        // المستودع والصنف يجب أن يكونا من صيدلية/فرع المستخدم
        \App\Models\Warehouse::findOrFail($validated['warehouse_id']);
        \App\Models\Product::findOrFail($validated['product_id']);

        ProductBatch::create($validated);

        return redirect()->route('batches.index')
            ->with('success', 'تم إضافة الدفعة بنجاح.');
    }
    // 3. تحديث دفعة من الصفحة العامة
    public function updateAll(Request $request, ProductBatch $batch)
    {
        $validated = $request->validate([
            'product_id'         => 'required|exists:products,id,deleted_at,NULL',
            'warehouse_id'       => 'required|exists:warehouses,id,deleted_at,NULL',
            'batch_number'       => 'required|string|max:100',
            'manufacturing_date' => 'nullable|date',
            'expiry_date'        => 'nullable|date|after_or_equal:manufacturing_date',
            'cost_price'         => 'required|numeric|min:0',
            'quantity'           => 'required|integer|min:0',
        ]);

        \App\Support\PriceGuard::check(['cost_price' => [$validated['product_id'], $validated['cost_price']]]);
        // المستودع والصنف يجب أن يكونا من صيدلية/فرع المستخدم
        \App\Models\Warehouse::findOrFail($validated['warehouse_id']);
        \App\Models\Product::findOrFail($validated['product_id']);

        $batch->update($validated);

        return redirect()->route('batches.index')
            ->with('success', 'تم تحديث الدفعة بنجاح.');
    }

    // 4. حذف دفعة من الصفحة العامة
    public function destroyAll(ProductBatch $batch)
    {
        $batch->delete();

        return redirect()->route('batches.index')
            ->with('success', 'تم حذف الدفعة بنجاح.');
    }
}