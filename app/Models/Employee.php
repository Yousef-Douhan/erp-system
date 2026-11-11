<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory, AuditLogsable, BelongsToTenant, SoftDeletes;

    protected static ?string $tenantOrg = 'organization_id';
    protected static ?string $tenantBranch = 'branch_id';

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
        return $this->belongsTo(Organization::class)->withTrashed();
    }

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'employee_branches')->withPivot('is_primary')->withTimestamps();
    }
}
