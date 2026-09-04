<?php

namespace App\Models;

use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles; // <-- تم تفعيل الاستدعاء
use App\Models\Notification; // <--- تأكد من إضافة هذا السطر هنا أيضاً

/**
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Notification[] $notifications
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Notification[] $unreadNotifications
 * @method \Illuminate\Database\Eloquent\Relations\HasMany notifications()
 * @method \Illuminate\Database\Eloquent\Relations\HasMany unreadNotifications()
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, AuditLogsable;

    protected $fillable = [
        'organization_id',
        'username',
        'email',
        'phone',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    // علاقة المستخدم مع المنظمة
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * العلاقة مع جدول الإشعارات المخصص
     */
    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * الإشعارات غير المقروءة فقط
     */
    public function unreadNotifications()
    {
        return $this->hasMany(Notification::class)->where('is_read', false);
    }
}