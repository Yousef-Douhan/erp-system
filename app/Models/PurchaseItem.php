<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    use HasFactory, AuditLogsable, BelongsToTenant, SoftDeletes;


    /** مرتبط بالصيدلية/الفرع عبر Purchase */
    protected static function tenantScope(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereIn($query->getModel()->getTable() . '.purchase_id', Purchase::query()->select('id'));
    }

    protected $fillable = [
        'purchase_id', 'product_id', 'batch_number', 'expiry_date',
        'quantity', 'unit_cost', 'total',
    ];

    // العلاقة مع الفاتورة الأساسية
    public function purchase()
    {
        return $this->belongsTo(Purchase::class)->withTrashed();
    }

    // العلاقة مع المنتج (إذا كان لديك جدول للمنتجات)
    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
