<?php

namespace App\Models;

use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    use HasFactory, AuditLogsable;

    protected $fillable = [
        'organization_id',
        'entry_number',
        'entry_date',
        'description',
        'ref_type',
        'ref_id',
    ];

    // المنظمة التابع لها القيد
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    // تفاصيل القيد (الأسطر الدائنة والمدיنة)
    public function lines()
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    // العلاقة المتعددة المرجعية (Polymorphic-like relation) لربطه بالفاتورة أو السند
    public function reference()
    {
        return $this->morphTo(__FUNCTION__, 'ref_type', 'ref_id');
    }
}
