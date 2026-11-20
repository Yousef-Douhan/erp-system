<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasFactory, AuditLogsable, BelongsToTenant, SoftDeletes;

    protected static ?string $tenantOrg = 'organization_id';
    protected static ?string $tenantBranch = 'branch_id';
    protected static bool $tenantBranchRequired = true;

    protected $fillable = [
        'organization_id', 'branch_id', 'supplier_id', 'employee_id',
        'invoice_number', 'total', 'paid_amount', 'returned_amount', 'status',
    ];

    /** الفاتورة لم تُسدَّد بالكامل (بعد خصم المرتجع للمورد) — للاستعمال في whereRaw */
    public const OPEN_SQL = 'paid_amount < total - COALESCE(returned_amount, 0) - 0.001';

    /** المتبقي علينا في الفاتورة — للاستعمال في selectRaw */
    public const DUE_SQL = '(total - COALESCE(returned_amount, 0) - paid_amount)';

    /** صافي الفاتورة بعد مرتجعات المورد */
    public function netTotal(): float
    {
        return round(max(0, (float) $this->total - (float) $this->returned_amount), 2);
    }

    /** المتبقي علينا للمورد على هذه الفاتورة */
    public function remaining(): float
    {
        return round(max(0, $this->netTotal() - (float) $this->paid_amount), 2);
    }

    public function returns()
    {
        return $this->hasMany(PurchaseReturn::class);
    }

    // العلاقة مع عناصر/تفاصيل الفاتورة (Purchase Items)
    public function items()
    {
        return $this->hasMany(PurchaseItem::class); // أو اسم المودل الخاص بتفاصيل الفاتورة لديك
    }

    // العلاقات الأخرى الموجودة لديك مسبقاً (مثل Supplier, Branch, Employee)
    public function supplier()
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id')->withTrashed();
    }

    public function payments()
    {
        return $this->hasMany(PurchasePayment::class);
    }
}
