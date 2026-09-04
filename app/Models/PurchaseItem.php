<?php

namespace App\Models;

use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    use HasFactory, AuditLogsable;

    protected $guarded = [];

    // العلاقة مع الفاتورة الأساسية
    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    // العلاقة مع المنتج (إذا كان لديك جدول للمنتجات)
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
