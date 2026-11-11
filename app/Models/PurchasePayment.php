<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Model;

class PurchasePayment extends Model
{
    use AuditLogsable, BelongsToTenant;


    /** مرتبط بالصيدلية/الفرع عبر Purchase */
    protected static function tenantScope(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereIn($query->getModel()->getTable() . '.purchase_id', Purchase::query()->select('id'));
    }

    protected $fillable = ['purchase_id', 'supplier_id', 'cash_register_id', 'user_id', 'amount', 'payment_method', 'reference', 'notes'];

    protected $casts = ['amount' => 'decimal:2'];

    public function purchase()
    {
        return $this->belongsTo(Purchase::class)->withTrashed();
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function cashRegister()
    {
        return $this->belongsTo(CashRegister::class)->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
