<?php

namespace App\Models;

use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use HasFactory, AuditLogsable;

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
        return $this->belongsTo(Organization::class);
    }

    // الحساب الأب (الشجرة المحاسبية)
    public function parent()
    {
        return $this->belongsTo(Account::class, 'parent_id');
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
