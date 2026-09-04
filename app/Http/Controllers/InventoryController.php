<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InventoryController extends Controller
{
    // عرض قائمة المنتجات
    public function index()
    {
        $items = Product::where('organization_id', auth()->user()->organization_id)
            ->with('category')
            ->latest()
            ->get();

        return view('inventory.index', compact('items'));
    }

    // عرض صفحة إضافة منتج
    public function create()
    {
        $categories = Category::all();
        return view('inventory.create', compact('categories'));
    }

    // حفظ المنتج الجديد
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'quantity' => 'required|integer|min:0',
            'price' => 'required|numeric|min:0',
        ]);

        $validated['organization_id'] = auth()->user()->organization_id;
        $validated['sku'] = 'SKU-' . strtoupper(Str::random(8));
        $validated['track_expiry'] = $request->has('track_expiry') ? 1 : 0;
        $validated['is_controlled'] = $request->has('is_controlled') ? 1 : 0;

        Product::create($validated);

        return redirect()->route('inventory.index')->with('success', 'تم إضافة المنتج بنجاح');
    }

    // عرض صفحة التعديل
    public function edit(Product $inventory)
    {
        $categories = Category::all();
        // نقوم بتمرير المتغير باسم $item للحفاظ على توافق واجهة الـ Blade
        $item = $inventory;
        return view('inventory.edit', compact('item', 'categories'));
    }

    // تحديث بيانات المنتج
    public function update(Request $request, Product $inventory)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'quantity' => 'required|integer|min:0',
            'price' => 'required|numeric|min:0',
        ]);

        $validated['organization_id'] = auth()->user()->organization_id;
        $validated['track_expiry'] = $request->has('track_expiry') ? 1 : 0;
        $validated['is_controlled'] = $request->has('is_controlled') ? 1 : 0;

        $inventory->update($validated);

        return redirect()->route('inventory.index')->with('success', 'تم تحديث المنتج بنجاح');
    }

    // حذف المنتج
    public function destroy(Product $inventory)
    {
        $inventory->delete();
        return redirect()->route('inventory.index')->with('success', 'تم حذف المنتج بنجاح');
    }
}
