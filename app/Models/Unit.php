<?php

namespace App\Models;

use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasFactory, AuditLogsable;

    protected $fillable = [
        'organization_id',
        'name',
        'short_name',
        'description',
        'is_active',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
    // public function products()
    // {
    //     return $this->hasMany(Product::class);
    // }   
   
}
