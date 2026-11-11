<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerAccount extends Model
{
    use HasFactory, AuditLogsable, BelongsToTenant;


    /** مرتبط بالصيدلية/الفرع عبر Customer */
    protected static function tenantScope(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereIn($query->getModel()->getTable() . '.customer_id', Customer::query()->select('id'));
    }

    protected $fillable = [
        'customer_id',
        'transaction_type',
        'debit',
        'credit',
        'balance',
    ];

    // العميل المرتبط بهذه الحركة المالية
    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }
}
