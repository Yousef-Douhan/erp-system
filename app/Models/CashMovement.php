<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * حركة على خزينة (وارد أو صادر). لا تُعدَّل ولا تُحذف: التصحيح يكون بحركة عكسية.
 */
class CashMovement extends Model
{
    use BelongsToTenant;

    protected static ?string $tenantBranch = 'branch_id';

    protected $fillable = [
        'cash_register_id', 'branch_id', 'cash_shift_id', 'user_id', 'type', 'direction',
        'amount', 'balance_after', 'reference_type', 'reference_id', 'description',
    ];

    protected $casts = [
        'amount'        => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function register()
    {
        return $this->belongsTo(CashRegister::class, 'cash_register_id')->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function shift()
    {
        return $this->belongsTo(CashShift::class, 'cash_shift_id');
    }

    public function isIn(): bool
    {
        return $this->direction === 'in';
    }

    public function typeLabel(): string
    {
        return \App\Services\CashBook::TYPES[$this->type] ?? $this->type;
    }

    /** رابط المستند المرتبط بالحركة (فاتورة، مرتجع، مصروف...) إن وُجد */
    public function referenceUrl(): ?string
    {
        if (!$this->reference_id) {
            return null;
        }

        return match ($this->reference_type) {
            'sale'        => route('sales.show', $this->reference_id),
            'purchase_return' => route('purchase-returns.show', $this->reference_id),
            'sale_return' => route('returns.show', $this->reference_id),
            'cash_shift'  => route('shifts.show', $this->reference_id),
            'purchase'    => route('purchases.index', ['show' => $this->reference_id]),
            default       => null,
        };
    }
}
