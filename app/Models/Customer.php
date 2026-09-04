<?php

namespace App\Models;

use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory, AuditLogsable;

    protected $fillable = [
        'organization_id',
        'name',
        'phone',
        'balance',
        'credit_limit',
    ];

    /**
     * العلاقة مع جدول المنظمات
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class, 'customer_id'); // تأكد أن المفتاح الأجنبي هو customer_id
    }
}
