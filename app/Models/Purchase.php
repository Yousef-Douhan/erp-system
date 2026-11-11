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
        'invoice_number', 'total', 'paid_amount', 'status',
    ];

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
