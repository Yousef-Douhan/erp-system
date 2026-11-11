<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\SaleService;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleItemController extends Controller
{
    /**
     * إضافة عنصر للفاتورة: يخصم من المخزون ويعيد حساب الإجمالي والحالة ودين العميل
     */
    public function store(Request $request, Sale $sale)
    {
        $this->authorize('update', $sale);

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id,deleted_at,NULL',
            'batch_id'   => 'nullable|exists:product_batches,id',
            'quantity'   => 'required|integer|min:1',
            'unit_price' => 'nullable|numeric|min:0',
            'discount'   => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () use ($sale, $validated) {
            $product = Product::findOrFail($validated['product_id']);

            // السعر الافتراضي من المنتج؛ يمكن تعديله يدوياً من شاشة الفاتورة
            $unitPrice = isset($validated['unit_price']) ? (float) $validated['unit_price'] : (float) $product->selling_price;
            $gross = $unitPrice * $validated['quantity'];
            $discount = min((float) ($validated['discount'] ?? 0), $gross);

            $item = $sale->items()->create([
                'product_id' => $product->id,
                'unit_name'  => $product->unit?->name,
                'unit_factor'=> 1,
                'quantity'   => $validated['quantity'],
                'unit_price' => $unitPrice,
                'discount'   => $discount,
                'total'      => round($gross - $discount, 2),
            ]);

            // صرف من الدفعات (الأقرب انتهاءً أولاً) مع حركة مخزنية
            $batchId = StockService::issueForSaleItem($item, (int) $validated['quantity']);
            $item->update(['batch_id' => $batchId]);

            SaleService::recalculate($sale);
        });

        return back()->with('success', 'تم إضافة المنتج إلى الفاتورة بنجاح.');
    }

    /**
     * تعديل عنصر: يسوي فرق الكمية مع المخزون
     */
    public function update(Request $request, SaleItem $saleItem)
    {
        $this->authorize('update', $saleItem->sale);

        if ((float) $saleItem->returned_quantity > 0) {
            return back()->with('error', 'لا يمكن تعديل صنف تم إرجاع جزء منه. استخدم المرتجعات بدلاً من ذلك.');
        }

        $validated = $request->validate([
            'quantity'   => 'required|integer|min:1',
            'unit_price' => 'required|numeric|min:0',
            'discount'   => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () use ($saleItem, $validated) {
            // عكس الصرف القديم بالكامل ثم صرف الكمية الجديدة من الدفعات
            $quantityChanged = (int) $validated['quantity'] !== (int) $saleItem->quantity;
            if ($quantityChanged) {
                StockService::reverseSaleItem($saleItem);
            }

            $gross = (float) $validated['unit_price'] * $validated['quantity'];
            $discount = min((float) ($validated['discount'] ?? 0), $gross);

            $saleItem->update([
                'quantity'   => $validated['quantity'],
                'unit_price' => $validated['unit_price'],
                'discount'   => $discount,
                'total'      => round($gross - $discount, 2),
            ]);

            if ($quantityChanged) {
                $baseQty = (int) round($validated['quantity'] * (float) ($saleItem->unit_factor ?: 1));
                $batchId = StockService::issueForSaleItem($saleItem, $baseQty);
                $saleItem->update(['batch_id' => $batchId]);
            }

            SaleService::recalculate($saleItem->sale);
        });

        return back()->with('success', 'تم تحديث عنصر الفاتورة بنجاح.');
    }

    /**
     * حذف عنصر: يرجع الكمية للمخزون
     */
    public function destroy(SaleItem $saleItem)
    {
        $this->authorize('update', $saleItem->sale);

        if ((float) $saleItem->returned_quantity > 0) {
            return back()->with('error', 'لا يمكن حذف صنف تم إرجاع جزء منه.');
        }

        DB::transaction(function () use ($saleItem) {
            $sale = $saleItem->sale;
            StockService::reverseSaleItem($saleItem);
            $saleItem->delete();

            SaleService::recalculate($sale);
        });

        return back()->with('success', 'تم حذف العنصر من الفاتورة وإرجاع الكمية للمخزون.');
    }
}
