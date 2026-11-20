<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory, AuditLogsable, BelongsToTenant, SoftDeletes;


    /** غير مدير النظام يرى صيدليته فقط */
    protected static function tenantScope(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('organizations.id', \App\Support\Tenancy::orgId() ?? 0);
    }

    protected $fillable = [
        'name',
        'currency',
        'timezone',
        'status',
        'require_shift',
    ];

    protected $casts = ['require_shift' => 'boolean'];

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
