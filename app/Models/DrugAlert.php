<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DrugAlert extends Model
{
    use HasFactory, AuditLogsable, BelongsToTenant;


    /** مرتبط بالصيدلية/الفرع عبر Product */
    protected static function tenantScope(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereIn($query->getModel()->getTable() . '.product_id', Product::query()->select('id'));
    }

    protected $fillable = [
        'product_id',
        'severity',
        'message',
    ];

    // علاقة التنبيه بالمنتج/الدواء
    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed(); // عدل اسم موديل المنتج إذا كان مختلفاً (مثلاً Medicine::class)
    }
}