<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prescription extends Model
{
    use HasFactory, AuditLogsable, BelongsToTenant, SoftDeletes;


    /** مرتبط بالصيدلية/الفرع عبر Customer */
    protected static function tenantScope(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereIn($query->getModel()->getTable() . '.customer_id', Customer::query()->select('id'));
    }

    protected $fillable = [
        'customer_id',
        'doctor_name',
        'diagnosis',
        'image_path',
        'is_filled',
    ];

    // العلاقة مع العملاء (فترض أن جدول العملاء اسمه Customer)
    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }
}
