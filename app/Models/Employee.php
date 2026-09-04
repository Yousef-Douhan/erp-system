<?php

namespace App\Models;

use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory, AuditLogsable;

    protected $fillable = [
        'organization_id',
        'user_id',
        'branch_id',
        'first_name',
        'last_name',
        'salary',
        'status',
    ];

    // علاقة الموظف مع المنظمة
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'employee_branches')->withPivot('is_primary')->withTimestamps();
    }
}
