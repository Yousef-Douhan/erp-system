<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Notifications\LowStockNotification;
use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryTransaction extends Model
{
    use HasFactory, AuditLogsable, BelongsToTenant;


    /** الحركة تتبع مستودعاً، والمستودع يتبع فرعاً */
    protected static function tenantScope(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereIn('inventory_transactions.warehouse_id', \App\Support\Tenancy::warehouseIds() ?: [0]);
    }

    protected $fillable = [
        'warehouse_id',
        'product_id',
        'batch_id',
        'type',
        'reference_type',
        'reference_id',
        'qty_in',
        'qty_out',
        'balance_after',
    ];

    protected $casts = [
        'qty_in'        => 'decimal:2',
        'qty_out'       => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    /**
     * كل حركة مخزنية تؤثر على رصيد المنتج العام وعلى رصيد فرع المستودع:
     * - الإنشاء: يضيف الوارد ويخصم الصادر
     * - التعديل: يعكس القيم القديمة ثم يطبق الجديدة
     * - الحذف: يعكس أثر الحركة
     */
    protected static function booted()
    {
        static::creating(function (InventoryTransaction $transaction) {
            $transaction->warehouse_id ??= \App\Support\Stock::warehouseFor(\App\Support\Tenancy::defaultBranchId());
            $branchId = \App\Support\Stock::branchOfWarehouse($transaction->warehouse_id);
            $before = \App\Support\Stock::quantity((int) $transaction->product_id, $branchId);
            $transaction->balance_after = $before + (float) $transaction->qty_in - (float) $transaction->qty_out;
        });

        static::created(function (InventoryTransaction $transaction) {
            $transaction->applyToStock($transaction->product_id, $transaction->warehouse_id, $transaction->qty_in, $transaction->qty_out, 1);
        });

        static::updating(function (InventoryTransaction $transaction) {
            // عكس أثر القيم القديمة قبل تطبيق الجديدة
            $transaction->applyToStock(
                $transaction->getOriginal('product_id'),
                $transaction->getOriginal('warehouse_id'),
                $transaction->getOriginal('qty_in'),
                $transaction->getOriginal('qty_out'),
                -1,
                false
            );

            $branchId = \App\Support\Stock::branchOfWarehouse($transaction->warehouse_id);
            $before = \App\Support\Stock::quantity((int) $transaction->product_id, $branchId);
            $transaction->balance_after = $before + (float) $transaction->qty_in - (float) $transaction->qty_out;
        });

        static::updated(function (InventoryTransaction $transaction) {
            $transaction->applyToStock($transaction->product_id, $transaction->warehouse_id, $transaction->qty_in, $transaction->qty_out, 1);
        });

        static::deleted(function (InventoryTransaction $transaction) {
            $transaction->applyToStock($transaction->product_id, $transaction->warehouse_id, $transaction->qty_in, $transaction->qty_out, -1, false);
        });
    }

    /**
     * تطبيق (أو عكس) أثر الحركة على رصيد المنتج ورصيد الفرع
     */
    public function applyToStock($productId, $warehouseId, $qtyIn, $qtyOut, int $direction, bool $notify = true): void
    {
        $product = \App\Support\Tenancy::bypass(fn () => Product::find($productId));
        if (!$product) {
            return;
        }

        $net = (int) round(((float) $qtyIn - (float) $qtyOut) * $direction);
        $branchId = \App\Support\Stock::branchOfWarehouse($warehouseId ? (int) $warehouseId : null);

        if ($net !== 0) {
            $product->increment('stock_quantity', $net);
            $product->refresh();

            if ($branchId) {
                \App\Support\Stock::adjust($branchId, (int) $productId, $net);
            }
        }

        if (!$notify || (float) $qtyOut <= 0) {
            return;
        }

        // تنبيه النقص: لرصيد الفرع إن وُجد، ولمستخدمي نفس الصيدلية فقط
        $level = $branchId ? \App\Support\Stock::quantity((int) $productId, $branchId) : (int) $product->stock_quantity;
        if ($level > (int) $product->alert_quantity) {
            return;
        }

        $users = User::where('is_active', true)
            ->where('organization_id', $product->organization_id)
            ->when($branchId, fn ($q) => $q->where(fn ($w) => $w->whereNull('branch_id')->orWhere('branch_id', $branchId)))
            ->get();

        $info = (object) ['warehouse_id' => $warehouseId, 'balance_after' => $level];
        foreach ($users as $user) {
            (new LowStockNotification($user, $product, $info))->send();
        }
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class)->withTrashed();
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function batch()
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id');
    }
}
