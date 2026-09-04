<?php

namespace App\Models;

use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory, AuditLogsable;

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

    // تفعيل أحداث الموديل لتسجيل العمليات أوتوماتيكياً في جدول الـ Audit Logs
    protected static function booted()
    {
        static::created(function ($product) {
            AuditLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'created',
                'table_name' => 'products',
                'record_id'  => $product->id,
                'ip_address' => request()->ip(),
                'new_values' => json_encode($product->toArray(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            ]);
        });

        static::updated(function ($product) {
            AuditLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'updated',
                'table_name' => 'products',
                'record_id'  => $product->id,
                'ip_address' => request()->ip(),
                'old_values' => json_encode($product->getOriginal(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                'new_values' => json_encode($product->getChanges(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            ]);
        });

        static::deleted(function ($product) {
            AuditLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'deleted',
                'table_name' => 'products',
                'record_id'  => $product->id,
                'ip_address' => request()->ip(),
                'old_values' => json_encode($product->toArray(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            ]);
        });
    }

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
        return $this->belongsTo(Organization::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function productUnits()
    {
        return $this->hasMany(ProductUnit::class);
    }
}
