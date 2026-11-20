<?php

namespace App\Models;

use App\Traits\AuditLogsable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * إتلاف كمية من المخزون (منتهية الصلاحية أو تالفة). لا يُحذف: هو سجل خسارة.
 */
class StockDisposal extends Model
{
    use AuditLogsable, BelongsToTenant;

    protected static ?string $tenantOrg = 'organization_id';
    protected static ?string $tenantBranch = 'branch_id';
    protected static bool $tenantBranchRequired = true;

    public const REASONS = [
        'expired' => 'منتهي الصلاحية',
        'damaged' => 'تالف / مكسور',
        'other'   => 'سبب آخر',
    ];

    protected $fillable = [
        'organization_id', 'branch_id', 'warehouse_id', 'product_id', 'batch_id', 'user_id',
        'quantity', 'unit_cost', 'total_cost', 'reason', 'notes',
    ];

    protected $casts = ['unit_cost' => 'decimal:4', 'total_cost' => 'decimal:2'];

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function batch()
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class)->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
