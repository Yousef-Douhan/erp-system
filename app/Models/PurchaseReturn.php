<?php

namespace App\Models;

use App\Traits\AuditLogsable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * مرتجع مشتريات للمورد. لا يُحذف ولا يُعدَّل: أي تصحيح يكون بفاتورة شراء جديدة.
 */
class PurchaseReturn extends Model
{
    use AuditLogsable, BelongsToTenant;

    protected static ?string $tenantOrg = 'organization_id';
    protected static ?string $tenantBranch = 'branch_id';
    protected static bool $tenantBranchRequired = true;

    protected $fillable = [
        'organization_id', 'branch_id', 'purchase_id', 'supplier_id', 'user_id', 'cash_register_id',
        'return_number', 'total', 'debt_reduction', 'cash_refund', 'supplier_credit', 'reason',
    ];

    protected $casts = [
        'total'           => 'decimal:2',
        'debt_reduction'  => 'decimal:2',
        'cash_refund'     => 'decimal:2',
        'supplier_credit' => 'decimal:2',
    ];

    public function purchase()
    {
        return $this->belongsTo(Purchase::class)->withTrashed();
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class)->withTrashed();
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
        return $this->hasMany(PurchaseReturnItem::class);
    }
}
