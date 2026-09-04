<?php

namespace App\Models;

use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierAccount extends Model
{
    use HasFactory, AuditLogsable;

    protected $fillable = [
        'supplier_id',
        'transaction_type',
        'debit',
        'credit',
        'balance',
    ];

    // المورد المرتبط بهذه الحركة المالية
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
