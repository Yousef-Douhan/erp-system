<?php

namespace App\Models;

use App\Traits\AuditLogsable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * دفعة بنك/محفظة تنتظر التأكيد من المسؤول أنها وصلت فعلاً (أو أننا حوّلناها للزبون في حالة المرتجع).
 */
class TransferCheck extends Model
{
    use AuditLogsable, BelongsToTenant;

    protected static ?string $tenantOrg = 'organization_id';
    protected static ?string $tenantBranch = 'branch_id';
    protected static bool $tenantBranchRequired = true;

    public const STATUSES = ['pending' => 'بانتظار المطابقة', 'confirmed' => 'وصلت ✓', 'rejected' => 'لم تصل'];
    public const SOURCES = ['sale' => 'فاتورة بيع', 'sale_payment' => 'دفعة على فاتورة', 'refund' => 'رد لزبون (مرتجع)'];

    protected $fillable = [
        'organization_id', 'branch_id', 'sale_id', 'customer_id', 'user_id', 'source', 'direction',
        'payment_method', 'wallet_phone', 'payer_name', 'payer_account', 'transfer_ref', 'amount', 'status', 'checked_by', 'checked_at', 'received_on', 'note', 'proof_path', 'statement_id',
    ];

    protected $casts = ['amount' => 'decimal:2', 'checked_at' => 'datetime', 'received_on' => 'date'];

    /** وصلت في يوم غير يوم الحوالة */
    public function arrivedLate(): bool
    {
        return $this->received_on && $this->received_on->toDateString() !== $this->created_at->toDateString();
    }

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

    public function checker()
    {
        return $this->belongsTo(User::class, 'checked_by')->withTrashed();
    }

    public function statement()
    {
        return $this->belongsTo(TransferStatement::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }
}
