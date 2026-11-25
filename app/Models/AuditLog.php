<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

/**
 * سجل التدقيق: يُكتب مرة واحدة ولا يُعدَّل ولا يُحذف، وكل صيدلية ترى سجلاتها فقط.
 */
class AuditLog extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected static ?string $tenantOrg = 'organization_id';

    protected $fillable = [
        'user_id',
        'organization_id',
        'action',
        'table_name',
        'record_id',
        'old_values',
        'new_values',
        'ip_address',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('سجل التدقيق لا يمكن تعديله.'));
        static::deleting(fn () => throw new LogicException('سجل التدقيق لا يمكن حذفه.'));
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
