<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierAccount extends Model
{
    use HasFactory, AuditLogsable, BelongsToTenant;


    /** مرتبط بالصيدلية/الفرع عبر Supplier */
    protected static function tenantScope(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereIn($query->getModel()->getTable() . '.supplier_id', Supplier::query()->select('id'));
    }

    protected $fillable = [
        'supplier_id',
        'transaction_type',
        'debit',
        'credit',
        'balance',
    ];

    // المورد المرتبط بهذه الحركة المالية
    public function supplier()
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }
}
