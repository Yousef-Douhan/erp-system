<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory, AuditLogsable, BelongsToTenant, SoftDeletes;

    protected static ?string $tenantOrg = 'organization_id';

    /** الصيدلية ترى فروعها، ومستخدم الفرع يرى فرعه فقط */
    protected static function tenantScope(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('branches.organization_id', \App\Support\Tenancy::orgId() ?? 0);
        if (\App\Support\Tenancy::branchId()) {
            $query->where('branches.id', \App\Support\Tenancy::branchId());
        }
    }


    protected $fillable = ['organization_id', 'name', 'type', 'phone', 'address', 'status'];

    public function organization()
    {
        return $this->belongsTo(Organization::class)->withTrashed();
    }

    /**
     * كل فرع جديد يأخذ مستودعاً خاصاً به تلقائياً
     */
    protected static function booted()
    {
        static::created(function (Branch $branch) {
            \App\Support\Stock::warehouseFor($branch->id);
        });
    }

    public function warehouses()
    {
        return $this->hasMany(Warehouse::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
