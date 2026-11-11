<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Model;

class StockTransfer extends Model
{
    use AuditLogsable, BelongsToTenant;


    /** التحويل يظهر لطرفيه: المستودع المرسل أو المستلم */
    protected static function tenantScope(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $ids = \App\Support\Tenancy::warehouseIds() ?: [0];
        $query->where(fn ($q) => $q->whereIn('stock_transfers.from_warehouse_id', $ids)->orWhereIn('stock_transfers.to_warehouse_id', $ids));
    }

    protected $fillable = ['transfer_number', 'from_warehouse_id', 'to_warehouse_id', 'user_id', 'notes'];

    public function items()
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function fromWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id')->withTrashed();
    }

    public function toWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id')->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
