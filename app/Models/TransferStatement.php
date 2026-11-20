<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** كشف حساب بنكي/محفظة مرفوع لتأكيد مجموعة حوالات دفعة واحدة */
class TransferStatement extends Model
{
    use BelongsToTenant;

    protected static ?string $tenantOrg = 'organization_id';
    protected static ?string $tenantBranch = 'branch_id';
    protected static bool $tenantBranchRequired = false;

    protected $fillable = ['organization_id', 'branch_id', 'user_id', 'payment_method', 'statement_date', 'file_path', 'original_name', 'note'];

    protected $casts = ['statement_date' => 'date'];

    public function checks()
    {
        return $this->hasMany(TransferCheck::class, 'statement_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
