<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductUnit extends Model
{
    use HasFactory, AuditLogsable, BelongsToTenant;


    /** مرتبط بالصيدلية/الفرع عبر Product */
    protected static function tenantScope(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereIn($query->getModel()->getTable() . '.product_id', Product::query()->select('id'));
    }
    protected $fillable = [
        'product_id',
        'unit_id',
        'conversion_factor',
        'purchase_price',
        'selling_price',
        'barcode',
        'is_default',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class)->withTrashed();
    }
    public function barcodes()
    {
        return $this->hasMany(ProductBarcode::class, 'product_unit_id');
    }
}
