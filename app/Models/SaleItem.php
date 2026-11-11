<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    use HasFactory, AuditLogsable, BelongsToTenant, SoftDeletes;


    /** مرتبط بالصيدلية/الفرع عبر Sale */
    protected static function tenantScope(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereIn($query->getModel()->getTable() . '.sale_id', Sale::query()->select('id'));
    }

    protected $fillable = [
        'sale_id',
        'product_id',
        'product_unit_id',
        'unit_name',
        'unit_factor',
        'batch_id',
        'quantity',
        'returned_quantity',
        'unit_price',
        'unit_cost',
        'discount',
        'total',
    ];

    // العلاقة مع فاتورة المبيعات الأساسية
    public function sale()
    {
        return $this->belongsTo(Sale::class)->withTrashed();
    }

    // العلاقة مع المنتج
    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function productUnit()
    {
        return $this->belongsTo(ProductUnit::class);
    }

    public function batch()
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id');
    }

    /**
     * الكمية التي ما زال يمكن إرجاعها من هذا السطر
     */
    public function returnableQuantity(): float
    {
        return max(0, round((float) $this->quantity - (float) $this->returned_quantity, 2));
    }
}
