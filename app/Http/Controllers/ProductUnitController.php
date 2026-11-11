<?php

namespace App\Http\Controllers;

use App\Models\ProductUnit;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Http\Request;

class ProductUnitController extends Controller
{
    public function index(Product $product)
    {
        $productUnits = $product->productUnits()->with('unit')->latest()->paginate(10);
        $units = Unit::all();

        return view('products.units.index', compact('product', 'productUnits', 'units'));
    }

    public function store(Request $request, Product $product)
    {
        $validated = $request->validate([
            'unit_id' => 'required|exists:units,id,deleted_at,NULL',
            'conversion_factor' => 'required|numeric|min:0.0001',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            // الباركود فريد داخل الصيدلية فقط
            'barcode' => ['nullable', 'string', 'max:100', \Illuminate\Validation\Rule::unique('product_units', 'barcode')->where(fn ($q) => $q->whereIn('product_id', \App\Models\Product::query()->select('id')))],
            'is_default' => 'nullable',
        ]);

        $isDefault = $request->has('is_default') ? 1 : 0;

        // إذا كانت الوحدة الجديدة افتراضية، نقوم بإلغاء الافتراضي عن باقي وحدات المنتج
        if ($isDefault) {
            $product->productUnits()->update(['is_default' => 0]);
        }

        ProductUnit::create([
            'product_id' => $product->id,
            'unit_id' => $validated['unit_id'],
            'conversion_factor' => $validated['conversion_factor'],
            'purchase_price' => $validated['purchase_price'],
            'selling_price' => $validated['selling_price'],
            'barcode' => $validated['barcode'] ?? null,
            'is_default' => $isDefault,
        ]);

        return redirect()->route('products.units.index', $product->id)->with('success', 'تم إضافة وحدة المنتج بنجاح.');
    }

    public function update(Request $request, Product $product, ProductUnit $unit)
    {
        $validated = $request->validate([
            'unit_id' => 'required|exists:units,id,deleted_at,NULL',
            'conversion_factor' => 'required|numeric|min:0.0001',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'barcode' => ['nullable', 'string', 'max:100', \Illuminate\Validation\Rule::unique('product_units', 'barcode')->where(fn ($q) => $q->whereIn('product_id', \App\Models\Product::query()->select('id')))->ignore($unit->id)],
            'is_default' => 'nullable',
        ]);

        $isDefault = $request->has('is_default') ? 1 : 0;

        if ($isDefault) {
            $product->productUnits()->update(['is_default' => 0]);
        }

        $unit->update([
            'unit_id' => $validated['unit_id'],
            'conversion_factor' => $validated['conversion_factor'],
            'purchase_price' => $validated['purchase_price'],
            'selling_price' => $validated['selling_price'],
            'barcode' => !empty($validated['barcode']) ? $validated['barcode'] : null,
            'is_default' => $isDefault,
        ]);

        return redirect()->route('products.units.index', $product->id)->with('success', 'تم تحديث وحدة المنتج بنجاح.');
    }

    public function destroy(Product $product, ProductUnit $unit)
    {
        $unit->delete();
        return redirect()->route('products.units.index', $product->id)->with('success', 'تم حذف وحدة المنتج بنجاح.');
    }
}
