<?php

namespace App\Models;

use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductBatch extends Model
{
    use HasFactory, AuditLogsable;

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'batch_number',
        'manufacturing_date',
        'expiry_date',
        'cost_price',
        'quantity',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class); // تم تصحيح حرف w الصغير إلى W كبير
    }
}
