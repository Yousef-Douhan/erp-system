<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
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
    use HasFactory, Notifiable, HasRoles, AuditLogsable, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'branch_id',
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

    // Laravel 10 يستخدم الخاصية $casts (الدالة casts() مدعومة فقط من Laravel 11)
    protected $casts = [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];

    // علاقة المستخدم مع المنظمة
    public function organization()
    {
        return $this->belongsTo(Organization::class)->withTrashed();
    }

    // الفرع الذي يعمل فيه (فارغ = على مستوى الصيدلية كلها)
    public function branch()
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function isSuperAdmin(): bool
    {
        return \App\Support\Tenancy::isSuperAdmin($this);
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