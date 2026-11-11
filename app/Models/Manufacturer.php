<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Manufacturer extends Model
{
    use HasFactory, AuditLogsable, BelongsToTenant, SoftDeletes;

    protected static ?string $tenantOrg = 'organization_id';

    protected $fillable = [
        'organization_id',
        'name',
        'contact_details',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class)->withTrashed();
    }

    // Laravel 10 يستخدم الخاصية $casts (الدالة casts() مدعومة فقط من Laravel 11)
    protected $casts = [
            'id' => 'integer',
        ];
}