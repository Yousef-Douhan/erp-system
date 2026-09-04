<?php

namespace App\Models;

use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashRegister extends Model
{
    use HasFactory, AuditLogsable;

    protected $fillable = [
        'branch_id',
        'name',
        'opening_balance',
        'current_balance',
        'status',
    ];

    // الفرع التابع له هذه الخزينة
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    // المصروفات المسحوبة من هذه الخزينة
    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }
}
