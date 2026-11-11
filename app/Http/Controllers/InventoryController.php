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
        $this->authorize('viewAny', Product::class);

        $items = Product::with('category')
            ->latest()
            ->get();
        \App\Support\Stock::overlay($items);

        return view('inventory.index', compact('items'));
    }

    // عرض صفحة إضافة منتج
    public function create()
    {
        $this->authorize('create', Product::class);

        $categories = Category::all();
        return view('inventory.create', compact('categories'));
    }

    // حفظ المنتج الجديد
    public function store(Request $request)
    {
        $this->authorize('create', Product::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id,deleted_at,NULL',
            'quantity' => 'required|integer|min:0',
            'price' => 'required|numeric|min:0',
        ]);

        $orgId = auth()->user()->organization_id ?? \App\Models\Organization::value('id');
        if (!$orgId) {
            return back()->withErrors('يجب إنشاء منظمة أولاً قبل إضافة المنتجات.')->withInput();
        }

        $product = Product::create([
            'organization_id' => $orgId,
            'name'            => $validated['name'],
            'category_id'     => $validated['category_id'],
            'stock_quantity'  => 0,
            'selling_price'   => $validated['price'],
            'sku'             => 'SKU-' . strtoupper(Str::random(8)),
            'track_expiry'    => $request->has('track_expiry'),
            'is_controlled'   => $request->has('is_controlled'),
            'is_active'       => true,
        ]);
        \App\Support\Stock::move($product->id, null, (int) $validated['quantity'], 'opening_stock', 'product', $product->id);

        return redirect()->route('inventory.index')->with('success', 'تم إضافة المنتج بنجاح');
    }

    // عرض صفحة التعديل
    public function edit(Product $inventory)
    {
        $this->authorize('update', $inventory);

        $categories = Category::all();
        // نقوم بتمرير المتغير باسم $item للحفاظ على توافق واجهة الـ Blade
        $item = $inventory;
        \App\Support\Stock::overlay(collect([$item]));
        return view('inventory.edit', compact('item', 'categories'));
    }

    // تحديث بيانات المنتج
    public function update(Request $request, Product $inventory)
    {
        $this->authorize('update', $inventory);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id,deleted_at,NULL',
            'quantity' => 'required|integer|min:0',
            'price' => 'required|numeric|min:0',
        ]);

        $inventory->update([
            'name'           => $validated['name'],
            'category_id'    => $validated['category_id'],
            'selling_price'  => $validated['price'],
            'track_expiry'   => $request->has('track_expiry'),
            'is_controlled'  => $request->has('is_controlled'),
        ]);

        $branchId = \App\Support\Stock::viewBranchId();
        $current = $branchId ? \App\Support\Stock::quantity($inventory->id, $branchId) : (int) $inventory->stock_quantity;
        $diff = (int) $validated['quantity'] - $current;
        if ($diff !== 0) {
            \App\Support\Stock::move($inventory->id, $branchId, $diff, 'adjustment', 'product', $inventory->id, $inventory->name);
        }

        return redirect()->route('inventory.index')->with('success', 'تم تحديث المنتج بنجاح');
    }

    // حذف المنتج
    public function destroy(Product $inventory)
    {
        $this->authorize('delete', $inventory);

        $inventory->delete();
        return redirect()->route('inventory.index')->with('success', 'تم حذف المنتج بنجاح');
    }
}
