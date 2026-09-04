<?php

namespace App\Models;

use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasFactory, AuditLogsable;

    protected $guarded = [];

    // العلاقة مع عناصر/تفاصيل الفاتورة (Purchase Items)
    public function items()
    {
        return $this->hasMany(PurchaseItem::class); // أو اسم المودل الخاص بتفاصيل الفاتورة لديك
    }

    // العلاقات الأخرى الموجودة لديك مسبقاً (مثل Supplier, Branch, Employee)
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }


  
}
