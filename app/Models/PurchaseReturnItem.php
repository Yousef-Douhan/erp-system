<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PurchaseReturnItem extends Model
{
    use BelongsToTenant;

    /** مرتبط بالصيدلية/الفرع عبر المرتجع */
    protected static function tenantScope(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereIn($query->getModel()->getTable() . '.purchase_return_id', PurchaseReturn::query()->select('id'));
    }

    protected $fillable = ['purchase_return_id', 'purchase_item_id', 'product_id', 'batch_id', 'quantity', 'unit_cost', 'total'];

    protected $casts = ['unit_cost' => 'decimal:4', 'total' => 'decimal:2'];

    public function purchaseReturn()
    {
        return $this->belongsTo(PurchaseReturn::class);
    }

    public function purchaseItem()
    {
        return $this->belongsTo(PurchaseItem::class)->withTrashed();
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function batch()
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id');
    }
}
