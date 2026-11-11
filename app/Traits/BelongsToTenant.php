<?php

namespace App\Traits;

use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;

/**
 * عزل البيانات بين الصيدليات والفروع.
 *
 * كل موديل يستخدم الـ Trait يحدد كيف يرتبط بالصيدلية/الفرع:
 *   protected static ?string $tenantOrg = 'organization_id';   // عمود الصيدلية (أو null)
 *   protected static ?string $tenantBranch = 'branch_id';      // عمود الفرع (أو null)
 *   protected static bool $tenantBranchRequired = true;        // يُملأ تلقائياً بأول فرع إذا تُرك فارغاً
 * أو يعيد تعريف tenantScope() إذا كان ارتباطه عبر جدول آخر (مثل أسطر الفاتورة عبر الفاتورة).
 *
 * النتيجة: أي استعلام أو ربط مسار (Route Model Binding) لا يرى إلا بيانات المستخدم الحالي،
 * وأي حفظ يُجبَر على صيدلية/فرع المستخدم. مدير النظام لا يُفلتر.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            if (Tenancy::active()) {
                static::tenantScope($query);
            }
        });

        static::saving(function ($model) {
            Tenancy::fill($model, static::tenantOrgColumn(), static::tenantBranchColumn(), static::$tenantBranchRequired ?? false);
        });
    }

    public static function tenantOrgColumn(): ?string
    {
        return property_exists(static::class, 'tenantOrg') ? static::$tenantOrg : null;
    }

    public static function tenantBranchColumn(): ?string
    {
        return property_exists(static::class, 'tenantBranch') ? static::$tenantBranch : null;
    }

    /**
     * الفلتر الافتراضي بالأعمدة. الموديلات المرتبطة عبر جدول آخر تعيد تعريفه.
     */
    protected static function tenantScope(Builder $query): void
    {
        $table = $query->getModel()->getTable();
        $org = static::tenantOrgColumn();
        $branch = static::tenantBranchColumn();

        Tenancy::constrain(
            $query,
            $org ? "{$table}.{$org}" : null,
            $branch ? "{$table}.{$branch}" : null
        );
    }

    /**
     * توافق مع الكود القديم: الفلترة صارت تلقائية، فهذا الـ scope لم يعد يضيف شيئاً
     */
    public function scopeVisibleTo(Builder $query, $user = null): Builder
    {
        return $query;
    }
}
