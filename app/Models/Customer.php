<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory, AuditLogsable, BelongsToTenant, SoftDeletes;

    protected static ?string $tenantOrg = 'organization_id';
    protected static ?string $tenantBranch = 'branch_id';
    protected static bool $tenantBranchRequired = true;

    protected $fillable = [
        'organization_id',
        'branch_id',
        'name',
        'phone',
        'balance',
        'credit_limit',
    ];

    /**
     * العلاقة مع جدول المنظمات
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class)->withTrashed();
    }

    public function prescriptions()
    {
        return $this->hasMany(Prescription::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class, 'customer_id'); // تأكد أن المفتاح الأجنبي هو customer_id
    }
}
