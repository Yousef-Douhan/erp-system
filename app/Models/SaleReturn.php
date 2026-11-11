<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Model;

class SaleReturn extends Model
{
    use AuditLogsable, BelongsToTenant;

    protected static ?string $tenantOrg = 'organization_id';
    protected static ?string $tenantBranch = 'branch_id';
    protected static bool $tenantBranchRequired = true;

    protected $fillable = [
        'organization_id', 'branch_id', 'sale_id', 'customer_id', 'user_id', 'cash_register_id',
        'return_number', 'total', 'refund_amount', 'debt_reduction', 'restocked', 'reason',
    ];

    protected $casts = [
        'total'          => 'decimal:2',
        'refund_amount'  => 'decimal:2',
        'debt_reduction' => 'decimal:2',
        'restocked'      => 'boolean',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class)->withTrashed();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function cashRegister()
    {
        return $this->belongsTo(CashRegister::class)->withTrashed();
    }

    public function items()
    {
        return $this->hasMany(SaleReturnItem::class);
    }
}
