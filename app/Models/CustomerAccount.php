<?php

namespace App\Models;

use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerAccount extends Model
{
    use HasFactory, AuditLogsable;

    protected $fillable = [
        'customer_id',
        'transaction_type',
        'debit',
        'credit',
        'balance',
    ];

    // العميل المرتبط بهذه الحركة المالية
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
