<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashRegister extends Model
{
    use HasFactory, AuditLogsable, BelongsToTenant, SoftDeletes;

    protected static ?string $tenantBranch = 'branch_id';
    protected static bool $tenantBranchRequired = true;

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
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    // المصروفات المسحوبة من هذه الخزينة
    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function shifts()
    {
        return $this->hasMany(CashShift::class);
    }

    public function openShift()
    {
        return $this->hasOne(CashShift::class)->where('status', 'open');
    }
}
