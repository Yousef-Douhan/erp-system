<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Model;

class StockCount extends Model
{
    use AuditLogsable, BelongsToTenant;

    protected static ?string $tenantOrg = 'organization_id';
    protected static ?string $tenantBranch = 'branch_id';
    protected static bool $tenantBranchRequired = true;

    protected $fillable = ['organization_id', 'branch_id', 'user_id', 'posted_by', 'count_number', 'scope', 'status', 'notes', 'posted_at'];

    protected $casts = ['posted_at' => 'datetime'];

    public function branch()
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function items()
    {
        return $this->hasMany(StockCountItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function poster()
    {
        return $this->belongsTo(User::class, 'posted_by')->withTrashed();
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }
}
