<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class StockTransferItem extends Model
{
    use BelongsToTenant;


    /** مرتبط بالصيدلية/الفرع عبر StockTransfer */
    protected static function tenantScope(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereIn($query->getModel()->getTable() . '.stock_transfer_id', StockTransfer::query()->select('id'));
    }

    protected $fillable = ['stock_transfer_id', 'product_id', 'from_batch_id', 'to_batch_id', 'quantity'];

    public function transfer()
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function fromBatch()
    {
        return $this->belongsTo(ProductBatch::class, 'from_batch_id');
    }
}
