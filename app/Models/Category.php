<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory, AuditLogsable, BelongsToTenant, SoftDeletes;

    protected static ?string $tenantOrg = 'organization_id';

    protected $fillable = ['organization_id', 'parent_id', 'name', 'image', 'is_active'];

    public function organization()
    {
        return $this->belongsTo(Organization::class)->withTrashed();
    }
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id')->withTrashed();
    }
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    // Laravel 10 يستخدم الخاصية $casts (الدالة casts() مدعومة فقط من Laravel 11)
    protected $casts = [
            'id' => 'integer',
            'is_active' => 'boolean',
        ];
}
