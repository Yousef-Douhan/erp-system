<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * رصيد الصنف في فرع. رصيد المنتج العام (products.stock_quantity) = مجموع أرصدة فروعه.
 * يتحدث تلقائياً من الحركات المخزنية (InventoryTransaction) حسب فرع المستودع.
 */
class BranchStock extends Model
{
    use BelongsToTenant;

    protected static ?string $tenantBranch = 'branch_id';

    protected $fillable = ['branch_id', 'product_id', 'quantity'];

    protected $casts = ['quantity' => 'integer'];

    public function branch()
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
