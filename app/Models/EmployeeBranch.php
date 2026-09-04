<?php

namespace App\Models;

use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeBranch extends Model
{
    use HasFactory, AuditLogsable;
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
        return $this->belongsTo(Employee::class);
    }

    // علاقة مع الفرع
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
