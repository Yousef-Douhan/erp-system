<?php

namespace App\Models;

use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory, AuditLogsable;

    protected $fillable = [
        'invoice_number',
        'customer_id',
        'branch_id',
        'organization_id',
        'employee_id',
        'total',
        'paid_amount',
        'discount',
        'status',
        'payment_method', // <--- تأكد من وجوده هنا
        'wallet_phone',   // <--- تأكد من وجوده هنا
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }
}
