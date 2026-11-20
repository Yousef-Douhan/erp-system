<?php

namespace App\Services;

use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\SaleItem;
use App\Models\Warehouse;

/**
 * صرف المخزون للمبيعات:
 * - يصرف من الدفعات حسب الأقرب انتهاءً أولاً (FEFO) ويتجاهل الدفعات المنتهية
 * - يسجل حركة مخزنية (inventory_transactions) لكل جزء، والحركة نفسها تخصم من رصيد المنتج
 *   وترسل تنبيه نقص المخزون
 * - يعكس كل ذلك عند حذف العنصر أو الفاتورة
 */
class StockService
{
    public const REF_SALE_ITEM = 'sale_item';
    public const REF_RETURN_ITEM = 'sale_return_item';

    /**
     * مستودع الفرع الافتراضي (فرع المستخدم الحالي إن لم يُحدد)
     */
    public static function defaultWarehouseId(?int $branchId = null): int
    {
        return \App\Support\Stock::warehouseFor($branchId ?? \App\Support\Tenancy::defaultBranchId());
    }

    /**
     * فرع الفاتورة التي يتبعها السطر
     */
    public static function branchOfItem(SaleItem $item): ?int
    {
        $branchId = \App\Support\Tenancy::bypass(fn () => \App\Models\Sale::whereKey($item->sale_id)->value('branch_id'));

        return $branchId ? (int) $branchId : null;
    }

    /**
     * الكمية المتاحة للبيع من الدفعات (غير المنتهية) في الفرع
     */
    public static function sellableBatchQuantity(int $productId, ?int $branchId = null): int
    {
        return (int) self::sellableBatches($productId, false, $branchId)->sum('quantity');
    }

    /**
     * صرف كمية (بالوحدة الأساسية) لعنصر فاتورة. يرجع رقم أول دفعة صُرف منها.
     *
     * $allowShortage: لفواتير البيع بدون إنترنت فقط — البضاعة خرجت فعلاً من الصيدلية،
     * فيُصرف المتاح من الدفعات والباقي بدون دفعة (يصير رصيد الفرع سالباً وتُعلَّم الفاتورة للمراجعة).
     */
    public static function issueForSaleItem(SaleItem $item, int $baseQty, bool $allowShortage = false): ?int
    {
        $product = Product::withTrashed()->whereKey($item->product_id)->lockForUpdate()->firstOrFail();
        $branchId = self::branchOfItem($item);

        $available = self::sellableQuantity($product, $branchId);
        if ($available < $baseQty && !$allowShortage) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'items' => "الكمية المطلوبة من ({$product->name}) أكبر من المتوفر في الفرع ({$available}).",
            ]);
        }

        $remaining = $baseQty;
        $firstBatchId = null;
        $cost = 0.0;
        $fallbackCost = (float) $product->purchase_price;

        foreach (self::sellableBatches($product->id, true, $branchId) as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $take = min((int) $batch->quantity, $remaining);
            $batch->decrement('quantity', $take);

            self::movement($item, $take, 0, $batch->warehouse_id, $batch->id, 'sale');

            // تكلفة الجزء المصروف من هذه الدفعة (أو سعر شراء المنتج إذا لم تُسجل تكلفة للدفعة)
            $cost += $take * ((float) $batch->cost_price > 0 ? (float) $batch->cost_price : $fallbackCost);

            $firstBatchId ??= $batch->id;
            $remaining -= $take;
        }

        // ما تبقى (مخزون غير مسجل في دفعات) يُصرف بدون دفعة من مستودع الفرع
        if ($remaining > 0) {
            self::movement($item, $remaining, 0, self::defaultWarehouseId($branchId), null, 'sale');
            $cost += $remaining * $fallbackCost;
        }

        // تكلفة وحدة البيع وقت البيع (تُستخدم في تقرير الأرباح)
        $qty = (float) $item->quantity;
        if ($qty > 0) {
            $item->forceFill(['unit_cost' => round($cost / $qty, 4)])->save();
        }

        return $firstBatchId;
    }

    /**
     * عكس صرف عنصر فاتورة: إرجاع الكميات للدفعات وللمخزون مع حركة "مرتجع مبيعات"
     */
    public static function reverseSaleItem(SaleItem $item): void
    {
        $moves = InventoryTransaction::where('reference_type', self::REF_SALE_ITEM)
            ->where('reference_id', $item->id)
            ->where('type', 'sale')
            ->get();

        // عناصر قديمة قبل الربط بالحركات المخزنية
        if ($moves->isEmpty()) {
            $baseQty = (int) round((float) $item->quantity * (float) ($item->unit_factor ?: 1));
            Product::whereKey($item->product_id)->increment('stock_quantity', $baseQty);
            return;
        }

        foreach ($moves as $move) {
            $qty = (int) round((float) $move->qty_out);

            if ($move->batch_id) {
                ProductBatch::whereKey($move->batch_id)->increment('quantity', $qty);
            }

            self::movement($item, 0, $qty, $move->warehouse_id, $move->batch_id, 'sale_return');
        }

        // تعليم الحركات الأصلية حتى لا تُعكس مرة ثانية (تحديث مباشر بدون أحداث الموديل)
        InventoryTransaction::whereIn('id', $moves->pluck('id'))->update(['type' => 'sale_reversed']);
    }

    /**
     * إرجاع كمية مرتجعة للمخزون: تعود لنفس الدفعات التي صُرفت منها (بالعكس)،
     * وما لا يمكن ربطه بدفعة يعود للرصيد العام.
     */
    public static function restockReturn(SaleItem $item, int $baseQty, int $returnItemId): void
    {
        $sold = InventoryTransaction::where('reference_type', self::REF_SALE_ITEM)
            ->where('reference_id', $item->id)
            ->where('type', 'sale')
            ->orderByDesc('id')
            ->get();

        // ما أُرجع سابقاً لكل دفعة من مرتجعات سابقة لنفس السطر
        $previousReturnIds = \App\Models\SaleReturnItem::where('sale_item_id', $item->id)
            ->where('id', '!=', $returnItemId)
            ->pluck('id');
        $alreadyBack = InventoryTransaction::where('reference_type', self::REF_RETURN_ITEM)
            ->whereIn('reference_id', $previousReturnIds)
            ->selectRaw('batch_id, SUM(qty_in) as qty')
            ->groupBy('batch_id')
            ->pluck('qty', 'batch_id');

        // الكمية المصروفة من كل دفعة (من الأحدث للأقدم) ومستودعها
        $soldPerBatch = [];
        $warehouseOf = [];
        foreach ($sold as $move) {
            if ($move->batch_id) {
                $soldPerBatch[$move->batch_id] = ($soldPerBatch[$move->batch_id] ?? 0) + (int) round((float) $move->qty_out);
                $warehouseOf[$move->batch_id] = $move->warehouse_id;
            }
        }

        $remaining = $baseQty;

        foreach ($soldPerBatch as $batchId => $qtySold) {
            if ($remaining <= 0) {
                break;
            }

            $room = $qtySold - (int) round((float) ($alreadyBack[$batchId] ?? 0));
            if ($room <= 0 || !ProductBatch::whereKey($batchId)->exists()) {
                continue;
            }

            $put = min($room, $remaining);
            ProductBatch::whereKey($batchId)->increment('quantity', $put);
            self::returnMovement($item, $put, $warehouseOf[$batchId], $batchId, $returnItemId);
            $remaining -= $put;
        }

        if ($remaining > 0) {
            self::returnMovement($item, $remaining, self::defaultWarehouseId(self::branchOfItem($item)), null, $returnItemId);
        }
    }

    private static function returnMovement(SaleItem $item, int $qty, ?int $warehouseId, ?int $batchId, int $returnItemId): void
    {
        InventoryTransaction::create([
            'warehouse_id'   => $warehouseId ?? self::defaultWarehouseId(self::branchOfItem($item)),
            'product_id'     => $item->product_id,
            'batch_id'       => $batchId,
            'type'           => 'sale_return',
            'reference_type' => self::REF_RETURN_ITEM,
            'reference_id'   => $returnItemId,
            'qty_in'         => $qty,
            'qty_out'        => 0,
        ]);
    }

    /**
     * كمية الدفعات المنتهية الصلاحية (موجودة في رصيد المنتج لكن لا يجوز بيعها)
     */
    public static function expiredBatchQuantity(int $productId, ?int $branchId = null): int
    {
        return (int) \App\Support\Tenancy::bypass(fn () => ProductBatch::where('product_id', $productId)
            ->when($branchId, fn ($q) => $q->whereIn('warehouse_id', \App\Support\Stock::warehouseIdsOfBranch($branchId) ?: [0]))
            ->where('quantity', '>', 0)
            ->whereDate('expiry_date', '<', today())
            ->sum('quantity'));
    }

    /**
     * الرصيد القابل للبيع = رصيد الفرع (أو الإجمالي) − الدفعات المنتهية فيه
     */
    public static function sellableQuantity(Product $product, ?int $branchId = null): int
    {
        $stock = $branchId ? \App\Support\Stock::quantity($product->id, $branchId) : (int) $product->stock_quantity;

        return max(0, $stock - self::expiredBatchQuantity($product->id, $branchId));
    }

    private static function sellableBatches(int $productId, bool $lock = false, ?int $branchId = null)
    {
        $query = ProductBatch::withoutGlobalScopes()->where('product_id', $productId)
            ->when($branchId, fn ($q) => $q->whereIn('warehouse_id', \App\Support\Stock::warehouseIdsOfBranch($branchId) ?: [0]))
            ->where('quantity', '>', 0)
            ->where(fn ($q) => $q->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', today()))
            ->orderByRaw('expiry_date IS NULL, expiry_date ASC')
            ->orderBy('id');

        return $lock ? $query->lockForUpdate()->get() : $query->get();
    }

    private static function movement(SaleItem $item, int $qtyOut, int $qtyIn, ?int $warehouseId, ?int $batchId, string $type): void
    {
        InventoryTransaction::create([
            'warehouse_id'   => $warehouseId ?? self::defaultWarehouseId(self::branchOfItem($item)),
            'product_id'     => $item->product_id,
            'batch_id'       => $batchId,
            'type'           => $type,
            'reference_type' => self::REF_SALE_ITEM,
            'reference_id'   => $item->id,
            'qty_in'         => $qtyIn,
            'qty_out'        => $qtyOut,
        ]);
    }
}
