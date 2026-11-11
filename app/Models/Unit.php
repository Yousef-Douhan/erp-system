<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasFactory, AuditLogsable, BelongsToTenant, SoftDeletes;

    protected static ?string $tenantOrg = 'organization_id';

    protected $fillable = [
        'organization_id',
        'name',
        'short_name',
        'description',
        'is_active',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class)->withTrashed();
    }
    // public function products()
    // {
    //     return $this->hasMany(Product::class);
    // }   
   
}
