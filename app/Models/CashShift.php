<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Model;

class CashShift extends Model
{
    use AuditLogsable, BelongsToTenant;


    /** مرتبط بالصيدلية/الفرع عبر CashRegister */
    protected static function tenantScope(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereIn($query->getModel()->getTable() . '.cash_register_id', CashRegister::query()->select('id'));
    }

    protected $fillable = [
        'cash_register_id', 'user_id', 'closed_by', 'opened_at', 'closed_at',
        'opening_balance', 'expected_balance', 'actual_balance', 'difference', 'status', 'notes',
    ];

    protected $casts = [
        'opened_at'        => 'datetime',
        'closed_at'        => 'datetime',
        'opening_balance'  => 'decimal:2',
        'expected_balance' => 'decimal:2',
        'actual_balance'   => 'decimal:2',
        'difference'       => 'decimal:2',
    ];

    public function cashRegister()
    {
        return $this->belongsTo(CashRegister::class)->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function closer()
    {
        return $this->belongsTo(User::class, 'closed_by')->withTrashed();
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
