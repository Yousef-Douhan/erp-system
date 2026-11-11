<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductBatch extends Model
{
    use HasFactory, AuditLogsable, BelongsToTenant;


    /** الدفعة تتبع مستودعاً، والمستودع يتبع فرعاً */
    protected static function tenantScope(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereIn('product_batches.warehouse_id', \App\Support\Tenancy::warehouseIds() ?: [0]);
    }

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'batch_number',
        'manufacturing_date',
        'expiry_date',
        'cost_price',
        'selling_price',
        'quantity',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class)->withTrashed(); // تم تصحيح حرف w الصغير إلى W كبير
    }
}
