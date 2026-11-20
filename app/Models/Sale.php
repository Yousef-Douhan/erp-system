<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToTenant;
use App\Traits\AuditLogsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory, AuditLogsable, BelongsToTenant, SoftDeletes;

    protected static ?string $tenantOrg = 'organization_id';
    protected static ?string $tenantBranch = 'branch_id';
    protected static bool $tenantBranchRequired = true;

    protected $fillable = [
        'invoice_number',
        'customer_name',
        'customer_id',
        'branch_id',
        'cash_register_id',
        'prescription_id',
        'organization_id',
        'employee_id',
        'total',
        'paid_amount',
        'returned_amount',
        'discount',
        'status',
        'payment_method', // <--- تأكد من وجوده هنا
        'wallet_phone',   // رقم جوال/محفظة المحوِّل
        'payer_name',     // اسم المحوِّل (بنك/محفظة)
        'payer_account',  // رقم حساب المحوِّل
        'transfer_ref',   // رقم العملية
        'client_uuid',    // رمز الفاتورة من جهاز البيع (بيع بدون إنترنت)
        'offline_number', // الرقم المؤقت المطبوع للزبون
        'offline_at',     // وقت البيع الفعلي على الجهاز
        'needs_review',
        'review_note',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'offline_at'   => 'datetime',
        'reviewed_at'  => 'datetime',
        'needs_review' => 'boolean',
    ];

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class)->withTrashed();
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id')->withTrashed();
    }
    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function cashRegister()
    {
        return $this->belongsTo(CashRegister::class)->withTrashed();
    }

    public function prescription()
    {
        return $this->belongsTo(Prescription::class)->withTrashed();
    }

    public function returns()
    {
        return $this->hasMany(SaleReturn::class);
    }
}
