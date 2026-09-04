<?php

namespace App\Models;

use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DrugAlert extends Model
{
    use HasFactory, AuditLogsable;

    protected $fillable = [
        'product_id',
        'severity',
        'message',
    ];

    // علاقة التنبيه بالمنتج/الدواء
    public function product()
    {
        return $this->belongsTo(Product::class); // عدل اسم موديل المنتج إذا كان مختلفاً (مثلاً Medicine::class)
    }
}