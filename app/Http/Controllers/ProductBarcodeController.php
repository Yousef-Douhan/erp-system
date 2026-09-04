<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductBarcode;
use Illuminate\Http\Request;

class ProductBarcodeController extends Controller
{
    // عرض باركودات وحدة معينة تابعة لمنتج
    public function index(Product $product, ProductUnit $unit)
    {
        $barcodes = $unit->barcodes()->latest()->paginate(10);
        return view('products.units.barcodes.index', compact('product', 'unit', 'barcodes'));
    }

    // حفظ باركود جديد
    public function store(Request $request, Product $product, ProductUnit $unit)
    {
        $validated = $request->validate([
            'barcode' => 'required|string|max:100|unique:product_barcodes,barcode',
            'type' => 'nullable|string|max:50',
        ]);

        $unit->barcodes()->create([
            'barcode' => $validated['barcode'],
            'type' => $validated['type'],
        ]);

        return redirect()->route('products.units.barcodes.index', [$product->id, $unit->id])
            ->with('success', 'تم إضافة الباركود بنجاح.');
    }

    // تحديث باركود موجود
    public function update(Request $request, Product $product, ProductUnit $unit, ProductBarcode $barcode)
    {
        $validated = $request->validate([
            'barcode' => 'required|string|max:100|unique:product_barcodes,barcode,' . $barcode->id,
            'type' => 'nullable|string|max:50',
        ]);

        $barcode->update([
            'barcode' => $validated['barcode'],
            'type' => $validated['type'],
        ]);

        return redirect()->route('products.units.barcodes.index', [$product->id, $unit->id])
            ->with('success', 'تم تحديث الباركود بنجاح.');
    }

    // حذف الباركود
    public function destroy(Product $product, ProductUnit $unit, ProductBarcode $barcode)
    {
        $barcode->delete();
        return redirect()->route('products.units.barcodes.index', [$product->id, $unit->id])
            ->with('success', 'تم حذف الباركود بنجاح.');
    }
}
