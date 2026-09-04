<?php

namespace App\Models;

use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory, AuditLogsable;


    protected $guarded = [];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
