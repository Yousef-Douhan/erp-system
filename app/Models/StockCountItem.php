<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class StockCountItem extends Model
{
    use BelongsToTenant;


    /** مرتبط بالصيدلية/الفرع عبر StockCount */
    protected static function tenantScope(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereIn($query->getModel()->getTable() . '.stock_count_id', StockCount::query()->select('id'));
    }

    protected $fillable = ['stock_count_id', 'product_id', 'system_qty', 'counted_qty', 'difference', 'unit_cost'];

    public function stockCount()
    {
        return $this->belongsTo(StockCount::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
