<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

/**
 * طباعة ملصقات باركود للأصناف (Code128)
 */
class BarcodeLabelController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::visibleTo($request->user())
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'barcode', 'sku', 'selling_price']);

        return view('barcodes.index', compact('products'));
    }

    public function print(Request $request)
    {
        $validated = $request->validate([
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id,deleted_at,NULL',
            'items.*.copies'     => 'required|integer|min:1|max:500',
            'size'               => 'nullable|in:38x25,50x30,58x40',
            'show_price'         => 'nullable|boolean',
        ]);

        $products = Product::visibleTo($request->user())
            ->whereIn('id', collect($validated['items'])->pluck('product_id'))
            ->get(['id', 'name', 'barcode', 'sku', 'selling_price'])
            ->keyBy('id');

        // صنف بلا باركود: يُنشأ له رمز ويُحفظ عليه، فيقرؤه قارئ نقطة البيع مباشرة
        foreach ($products as $p) {
            if (!$p->barcode) {
                $code = 'P' . str_pad($p->id, 6, '0', STR_PAD_LEFT);
                if (!Product::where('barcode', $code)->exists()) {
                    $p->update(['barcode' => $code]);
                }
            }
        }

        $labels = collect($validated['items'])->flatMap(function ($row) use ($products) {
            $p = $products[$row['product_id']] ?? null;
            if (!$p) {
                return [];
            }
            $code = $p->barcode ?: 'P' . str_pad($p->id, 6, '0', STR_PAD_LEFT);

            return array_fill(0, (int) $row['copies'], ['name' => $p->name, 'code' => $code, 'price' => (float) $p->selling_price]);
        })->take(2000);

        $size = $validated['size'] ?? '38x25';
        $showPrice = $request->boolean('show_price', true);

        return view('barcodes.print', compact('labels', 'size', 'showPrice'));
    }
}
