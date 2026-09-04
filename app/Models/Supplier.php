<?php

namespace App\Models;

use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory, AuditLogsable;

    protected $fillable = [
        'organization_id',
        'name',
        'company_name',
        'balance',
        'credit_limit',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
