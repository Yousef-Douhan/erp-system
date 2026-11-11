<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory, AuditLogsable, BelongsToTenant, SoftDeletes;

    protected static ?string $tenantOrg = 'organization_id';

    protected $fillable = [
        'organization_id',
        'category_id',
        'brand_id',
        'unit_id',
        'name',
        'sku',
        'barcode',
        'purchase_price',
        'selling_price',
        'stock_quantity',
        'alert_quantity',
        'track_expiry',
        'is_controlled',
        'expiry_date',
        'description',
        'is_active',
    ];

    // سجل التدقيق يتم تلقائياً عبر AuditLogsable (كان مكرراً هنا)

    // إضافة Accessors لضمان توافق أي استدعاء قديم لـ stock مع الحقل الحقيقي stock_quantity
    public function getStockAttribute()
    {
        return $this->stock_quantity;
    }

    public function setStockAttribute($value)
    {
        $this->attributes['stock_quantity'] = $value;
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class)->withTrashed();
    }

    public function category()
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class)->withTrashed();
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class)->withTrashed();
    }

    public function productUnits()
    {
        return $this->hasMany(ProductUnit::class);
    }

    public function batches()
    {
        return $this->hasMany(ProductBatch::class);
    }

    public function drugAlerts()
    {
        return $this->hasMany(DrugAlert::class);
    }
}
