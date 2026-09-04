<?php

namespace App\Models;

use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductStockSetting extends Model
{
    use HasFactory, AuditLogsable;

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'min_qty',
        'max_qty',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
