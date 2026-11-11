<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JournalEntryLine extends Model
{
    use HasFactory, AuditLogsable, BelongsToTenant, SoftDeletes;


    /** مرتبط بالصيدلية/الفرع عبر JournalEntry */
    protected static function tenantScope(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereIn($query->getModel()->getTable() . '.journal_entry_id', JournalEntry::query()->select('id'));
    }

    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'debit',
        'credit',
    ];

    // القيد الرئيسي التابع له هذا السطر
    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class)->withTrashed();
    }

    // الحساب المحاسبي المرتبط بهذا السطر
    public function account()
    {
        return $this->belongsTo(Account::class)->withTrashed();
    }
}
