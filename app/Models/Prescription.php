<?php

namespace App\Models;

use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prescription extends Model
{
    use HasFactory, AuditLogsable;

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
        return $this->belongsTo(Customer::class);
    }
}
