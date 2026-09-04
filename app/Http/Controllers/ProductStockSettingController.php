<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Warehouse;
use App\Models\ProductStockSetting;
use Illuminate\Http\Request;

class ProductStockSettingController extends Controller
{
    // عرض جميع الإعدادات مع المنتجات والمستودعات
    public function index()
    {
        $settings = ProductStockSetting::with(['product', 'warehouse'])->latest()->paginate(10);
        $products = Product::all();
        $warehouses = Warehouse::all();

        return view('stock_settings.index', compact('settings', 'products', 'warehouses'));
    }

    // حفظ إعداد جديد
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id'   => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'min_qty'      => 'required|integer|min:0',
            'max_qty'      => 'required|integer|min:0|gte:min_qty',
        ]);

        ProductStockSetting::create($validated);

        return redirect()->route('stock-settings.index')
            ->with('success', 'تم إضافة إعداد المخزون بنجاح.');
    }

    // تحديث الإعداد
    public function update(Request $request, ProductStockSetting $stockSetting)
    {
        $validated = $request->validate([
            'product_id'   => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'min_qty'      => 'required|integer|min:0',
            'max_qty'      => 'required|integer|min:0|gte:min_qty',
        ]);

        $stockSetting->update($validated);

        return redirect()->route('stock-settings.index')
            ->with('success', 'تم تحديث إعداد المخزون بنجاح.');
    }

    // حذف الإعداد
    public function destroy(ProductStockSetting $stockSetting)
    {
        $stockSetting->delete();

        return redirect()->route('stock-settings.index')
            ->with('success', 'تم حذف إعداد المخزون بنجاح.');
    }
}
