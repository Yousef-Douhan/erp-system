<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use HasFactory, AuditLogsable, BelongsToTenant, SoftDeletes;

    protected static ?string $tenantOrg = 'organization_id';

    protected $fillable = [
        'organization_id',
        'parent_id',
        'name',
        'account_code',
        'account_type',
    ];

    // المنظمة التابع لها الحساب
    public function organization()
    {
        return $this->belongsTo(Organization::class)->withTrashed();
    }

    // الحساب الأب (الشجرة المحاسبية)
    public function parent()
    {
        return $this->belongsTo(Account::class, 'parent_id')->withTrashed();
    }

    // الحسابات الابنة التابعة لهذا الحساب
    public function children()
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    // سطور قيود اليومية المرتبطة بهذا الحساب
    public function journalEntryLines()
    {
        return $this->hasMany(JournalEntryLine::class);
    }
}
