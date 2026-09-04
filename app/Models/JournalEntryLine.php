<?php

namespace App\Models;

use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JournalEntryLine extends Model
{
    use HasFactory, AuditLogsable;

    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'debit',
        'credit',
    ];

    // القيد الرئيسي التابع له هذا السطر
    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    // الحساب المحاسبي المرتبط بهذا السطر
    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
