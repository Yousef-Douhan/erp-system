<?php

namespace App\Models;

use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    use HasFactory, AuditLogsable;

    protected $fillable = [
        'sale_id',
        'product_id',
        'batch_id',
        'quantity',
        'unit_price',
        'discount',
        'total',
    ];

    // العلاقة مع فاتورة المبيعات الأساسية
    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    // العلاقة مع المنتج
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
