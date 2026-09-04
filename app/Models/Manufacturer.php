<?php

namespace App\Models;

use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Manufacturer extends Model
{
    use HasFactory, AuditLogsable;

    protected $fillable = [
        'organization_id',
        'name',
        'contact_details',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    protected function casts(): array
    {
        return [
            'id' => 'integer',
        ];
    }
}