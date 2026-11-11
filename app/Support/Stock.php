<?php

namespace App\Support;

use App\Models\BranchStock;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

/**
 * أرصدة المخزون حسب الفرع
 */
class Stock
{
    /**
     * المستودع الافتراضي للفرع (يُنشأ تلقائياً إن لم يوجد)
     */
    public static function warehouseFor(?int $branchId): int
    {
        return Tenancy::bypass(function () use ($branchId) {
            if ($branchId) {
                $id = Warehouse::where('branch_id', $branchId)->orderBy('id')->value('id');
                if ($id) {
                    return (int) $id;
                }
                $name = \App\Models\Branch::whereKey($branchId)->value('name');

                return (int) Warehouse::create(['name' => 'مستودع ' . ($name ?: $branchId), 'branch_id' => $branchId])->id;
            }

            return (int) (Warehouse::orderBy('id')->value('id') ?? Warehouse::create(['name' => 'المستودع الرئيسي'])->id);
        });
    }

    public static function branchOfWarehouse(?int $warehouseId): ?int
    {
        if (!$warehouseId) {
            return null;
        }

        return Tenancy::bypass(fn () => ($id = Warehouse::whereKey($warehouseId)->value('branch_id')) ? (int) $id : null);
    }

    public static function warehouseIdsOfBranch(int $branchId): array
    {
        return Tenancy::bypass(fn () => Warehouse::where('branch_id', $branchId)->pluck('id')->map(fn ($v) => (int) $v)->all());
    }

    /**
     * تعديل رصيد الفرع (+/−)
     */
    public static function adjust(int $branchId, int $productId, int $delta): void
    {
        if ($delta === 0) {
            return;
        }

        Tenancy::bypass(function () use ($branchId, $productId, $delta) {
            $affected = BranchStock::where('branch_id', $branchId)->where('product_id', $productId)
                ->update(['quantity' => DB::raw('quantity + (' . (int) $delta . ')'), 'updated_at' => now()]);

            if (!$affected) {
                BranchStock::create(['branch_id' => $branchId, 'product_id' => $productId, 'quantity' => $delta]);
            }
        });
    }

    public static function quantity(int $productId, ?int $branchId): int
    {
        if (!$branchId) {
            return (int) Tenancy::bypass(fn () => Product::whereKey($productId)->value('stock_quantity'));
        }

        return (int) Tenancy::bypass(fn () => BranchStock::where('branch_id', $branchId)->where('product_id', $productId)->value('quantity'));
    }

    /**
     * حركة وارد/صادر على فرع (تحدّث رصيد الفرع والرصيد العام عبر الحركة المخزنية).
     * $qty موجب = وارد، سالب = صادر. عند الصادر يتحقق من كفاية رصيد الفرع.
     */
    public static function move(int $productId, ?int $branchId, int $qty, string $type, ?string $refType = null, ?int $refId = null, ?string $productName = null): void
    {
        if ($qty === 0) {
            return;
        }

        $branchId ??= Tenancy::defaultBranchId();

        if ($qty < 0) {
            $available = self::quantity($productId, $branchId);
            if ($available < abs($qty)) {
                $name = $productName ?? Tenancy::bypass(fn () => Product::whereKey($productId)->value('name'));
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'quantity' => "لا يمكن سحب " . abs($qty) . " من ({$name}): المتوفر في الفرع {$available} فقط (ربما بِيع جزء منه).",
                ]);
            }
        }

        \App\Models\InventoryTransaction::create([
            'warehouse_id'   => self::warehouseFor($branchId),
            'product_id'     => $productId,
            'type'           => $type,
            'reference_type' => $refType,
            'reference_id'   => $refId,
            'qty_in'         => max(0, $qty),
            'qty_out'        => max(0, -$qty),
        ]);
    }

    /**
     * الفرع الذي تُعرض أرصدته للمستخدم الحالي: فرعه إن كان مستخدم فرع، وإلا null (الإجمالي)
     */
    public static function viewBranchId(): ?int
    {
        return Tenancy::active() ? Tenancy::branchId() : null;
    }

    /**
     * استبدال رصيد المنتج المعروض برصيد الفرع (للعرض فقط — لا تحفظ هذه الموديلات بعدها)
     */
    public static function overlay($products, ?int $branchId = null): void
    {
        $branchId ??= self::viewBranchId();
        if (!$branchId) {
            return;
        }

        $items = $products instanceof \Illuminate\Contracts\Pagination\Paginator ? collect($products->items()) : collect($products);
        $ids = $items->pluck('id')->filter()->all();
        if (!$ids) {
            return;
        }

        $qty = Tenancy::bypass(fn () => BranchStock::where('branch_id', $branchId)->whereIn('product_id', $ids)->pluck('quantity', 'product_id'));
        foreach ($items as $p) {
            $p->setAttribute('stock_quantity', (int) ($qty[$p->id] ?? 0));
            $p->syncOriginalAttribute('stock_quantity');
        }
    }

    /**
     * استعلام الأصناف التي وصلت للحد الأدنى: في فرع المستخدم، أو إجمالي الصيدلية
     */
    public static function lowStockQuery(?int $branchId = null)
    {
        $branchId ??= self::viewBranchId();
        $q = Product::query()->where('products.is_active', true);

        if ($branchId) {
            return $q->leftJoin('branch_stocks as bs', fn ($j) => $j->on('bs.product_id', '=', 'products.id')->where('bs.branch_id', $branchId))
                ->whereRaw('COALESCE(bs.quantity, 0) <= products.alert_quantity')
                ->select('products.*', DB::raw('COALESCE(bs.quantity, 0) as stock_quantity'));
        }

        return $q->whereColumn('products.stock_quantity', '<=', 'products.alert_quantity');
    }
}
