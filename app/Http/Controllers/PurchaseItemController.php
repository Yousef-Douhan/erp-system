<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Services\SaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseItemController extends Controller
{
    // تخزين عنصر جديد تابع لفاتورة شراء (البضاعة تدخل المخزون)
    public function store(Request $request, Purchase $purchase)
    {
        $this->authorize('update', $purchase);

        $validated = $this->validateItem($request);
        $validated['total'] = $validated['quantity'] * $validated['unit_cost'];
        $validated['purchase_id'] = $purchase->id;

        DB::transaction(function () use ($validated, $purchase) {
            $item = PurchaseItem::create($validated);

            $this->receive($purchase, $item);
            Product::whereKey($validated['product_id'])->update(['purchase_price' => $validated['unit_cost']]);

            $this->updatePurchaseTotal($purchase);
        });

        return redirect()->back()->with('success', 'تم إضافة المنتج إلى الفاتورة وزيادة المخزون بنجاح.');
    }

    // تحديث عنصر في فاتورة الشراء (تسوية فرق الكمية مع المخزون)
    public function update(Request $request, PurchaseItem $purchaseItem)
    {
        $this->authorize('update', $purchaseItem->purchase);

        $validated = $this->validateItem($request);
        $validated['total'] = $validated['quantity'] * $validated['unit_cost'];

        DB::transaction(function () use ($validated, $purchaseItem) {
            // عكس أثر الكمية القديمة (يرفض إذا كانت قد بيعت) ثم إضافة الجديدة في فرع الفاتورة
            $this->unreceive($purchaseItem->purchase, $purchaseItem);
            $purchaseItem->update($validated);
            $this->receive($purchaseItem->purchase, $purchaseItem);

            $this->updatePurchaseTotal($purchaseItem->purchase);
        });

        return redirect()->back()->with('success', 'تم تحديث العنصر بنجاح.');
    }

    // حذف عنصر من فاتورة الشراء (سحب الكمية من المخزون)
    public function destroy(PurchaseItem $purchaseItem)
    {
        $this->authorize('update', $purchaseItem->purchase);

        DB::transaction(function () use ($purchaseItem) {
            $purchase = $purchaseItem->purchase;

            $this->unreceive($purchase, $purchaseItem);
            $purchaseItem->delete();

            $this->updatePurchaseTotal($purchase);
        });

        return redirect()->back()->with('success', 'تم حذف العنصر من الفاتورة.');
    }

    private function validateItem(Request $request): array
    {
        $validated = $request->validate([
            'product_id'   => 'required|exists:products,id,deleted_at,NULL',
            'batch_number' => 'nullable|string|max:255',
            'expiry_date'  => 'nullable|date',
            'quantity'     => 'required|integer|min:1',
            'unit_cost'    => 'required|numeric|min:0',
        ]);

        // الكمية والسعر بالحبة (الوحدة الأساسية) لأن الكمية تُضاف مباشرة لرصيد الحبات
        \App\Support\PriceGuard::check(['unit_cost' => [$validated['product_id'], $validated['unit_cost']]]);

        return $validated;
    }

    /**
     * إدخال البضاعة لمستودع فرع الفاتورة: دفعة جديدة (إن وُجد رقم دفعة) + حركة وارد
     */
    private function receive(Purchase $purchase, PurchaseItem $item): void
    {
        $warehouseId = \App\Support\Stock::warehouseFor((int) $purchase->branch_id);
        $batchId = null;

        if ($item->batch_number) {
            $batch = \App\Models\ProductBatch::withoutGlobalScopes()->firstOrNew([
                'product_id'   => $item->product_id,
                'warehouse_id' => $warehouseId,
                'batch_number' => $item->batch_number,
            ]);
            $batch->expiry_date = $item->expiry_date ?: $batch->expiry_date;
            $batch->cost_price = $item->unit_cost;
            $batch->quantity = (int) $batch->quantity + (int) $item->quantity;
            $batch->save();
            $batchId = $batch->id;
        }

        \App\Models\InventoryTransaction::create([
            'warehouse_id'   => $warehouseId,
            'product_id'     => $item->product_id,
            'batch_id'       => $batchId,
            'type'           => 'purchase',
            'reference_type' => 'purchase_item',
            'reference_id'   => $item->id,
            'qty_in'         => (int) $item->quantity,
            'qty_out'        => 0,
        ]);
    }

    /**
     * سحب كمية سطر الشراء من الفرع (يرفض إذا بيعت)
     */
    private function unreceive(Purchase $purchase, PurchaseItem $item): void
    {
        \App\Support\Stock::move((int) $item->product_id, (int) $purchase->branch_id, -(int) $item->quantity, 'purchase_reversed', 'purchase_item', $item->id);

        if ($item->batch_number) {
            \App\Models\ProductBatch::withoutGlobalScopes()
                ->where('product_id', $item->product_id)
                ->where('warehouse_id', \App\Support\Stock::warehouseFor((int) $purchase->branch_id))
                ->where('batch_number', $item->batch_number)
                ->where('quantity', '>=', (int) $item->quantity)
                ->limit(1)
                ->decrement('quantity', (int) $item->quantity);
        }
    }

    // دالة مساعدة لإعادة حساب إجمالي فاتورة الشراء وحالتها
    private function updatePurchaseTotal(Purchase $purchase): void
    {
        $total = (float) $purchase->items()->sum('total');

        $purchase->update([
            'total'  => $total,
            'status' => SaleService::statusFor($total, (float) $purchase->paid_amount),
        ]);
    }
}
