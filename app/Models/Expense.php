<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory, AuditLogsable, BelongsToTenant, SoftDeletes;

    protected static ?string $tenantBranch = 'branch_id';
    protected static bool $tenantBranchRequired = true;

    protected $fillable = [
        'branch_id',
        'category_id',
        'cash_register_id',
        'amount',
        'expense_date',
        'description',
    ];

    // الفرع الذي حدث فيه المصروف
    public function branch()
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    // الخزينة التي تم الصرف منها
    public function cashRegister()
    {
        return $this->belongsTo(CashRegister::class)->withTrashed();
    }
}
