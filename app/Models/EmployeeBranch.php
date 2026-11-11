<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeBranch extends Model
{
    use HasFactory, AuditLogsable, BelongsToTenant;


    /** مرتبط بالصيدلية/الفرع عبر Employee */
    protected static function tenantScope(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereIn($query->getModel()->getTable() . '.employee_id', Employee::query()->select('id'));
    }
    protected $fillable = [
        'employee_id',
        'branch_id',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    // علاقة مع الموظف
    public function employee()
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    // علاقة مع الفرع
    public function branch()
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }
}
