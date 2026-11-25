<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * سياق الصيدلية/الفرع للمستخدم الحالي.
 *
 * ثلاث مستويات:
 *  - مدير النظام (دور Admin): يرى كل الصيدليات، لا يُطبَّق عليه أي فلتر.
 *  - مستخدم على مستوى الصيدلية (له organization_id بدون branch_id): يرى كل فروع صيدليته فقط.
 *  - مستخدم فرع (له branch_id): يرى فرعه فقط.
 *
 * الفلترة تُطبَّق تلقائياً عبر الـ Trait (BelongsToTenant) على كل استعلام وعلى ربط المسارات بالموديلات.
 */
class Tenancy
{
    private static array $cache = [];

    private static int $bypass = 0;

    public const ADMIN_ROLE = 'Admin';

    /**
     * المستخدم الحالي بدون إجبار تحميله (لتجنب الدوران عند تسجيل الدخول)
     */
    public static function user(): ?User
    {
        return Auth::hasUser() ? Auth::user() : null;
    }

    /**
     * هل الفلترة مفعّلة الآن؟ (يوجد مستخدم وليس مدير نظام)
     */
    public static function active(): bool
    {
        if (self::$bypass > 0) {
            return false;
        }
        $user = self::user();

        return $user !== null && ! self::isSuperAdmin($user);
    }

    public static function isSuperAdmin(?User $user = null): bool
    {
        $user ??= self::user();
        if (! $user) {
            return false;
        }

        return self::$cache['admin:'.$user->id] ??= $user->hasRole(self::ADMIN_ROLE);
    }

    public static function orgId(): ?int
    {
        $user = self::user();

        return $user && $user->organization_id ? (int) $user->organization_id : null;
    }

    public static function branchId(): ?int
    {
        $user = self::user();

        return $user && $user->branch_id ? (int) $user->branch_id : null;
    }

    /**
     * الفروع المسموحة للمستخدم الحالي (فرعه، أو كل فروع صيدليته)
     */
    public static function branchIds(): array
    {
        if ($id = self::branchId()) {
            return [$id];
        }
        $org = self::orgId();
        if (! $org) {
            return [];
        }

        return self::$cache['branches:'.$org] ??= Branch::withoutGlobalScope('tenant')
            ->where('organization_id', $org)->pluck('id')->map(fn ($v) => (int) $v)->all();
    }

    /**
     * كل فروع صيدلية المستخدم (حتى لو كان مستخدم فرع) — للتحويل بين الفروع مثلاً
     */
    public static function orgBranchIds(): array
    {
        $org = self::orgId();
        if (! $org) {
            return [];
        }

        return self::$cache['branches:'.$org] ??= Branch::withoutGlobalScope('tenant')
            ->where('organization_id', $org)->pluck('id')->map(fn ($v) => (int) $v)->all();
    }

    public static function warehouseIds(): array
    {
        $key = 'warehouses:'.implode(',', self::branchIds());

        return self::$cache[$key] ??= Warehouse::withoutGlobalScope('tenant')
            ->whereIn('branch_id', self::branchIds() ?: [0])->pluck('id')->map(fn ($v) => (int) $v)->all();
    }

    /**
     * تنفيذ كود بدون فلترة (عمليات النظام الداخلية)
     */
    public static function bypass(callable $callback)
    {
        self::$bypass++;
        try {
            return $callback();
        } finally {
            self::$bypass--;
        }
    }

    public static function flush(): void
    {
        self::$cache = [];
    }

    // ===================== أدوات الفلترة =====================

    /**
     * فلتر بعمود المنظمة و/أو الفرع على أي استعلام (مفيد مع الـ joins)
     */
    public static function constrain($query, ?string $orgColumn, ?string $branchColumn = null)
    {
        if (! self::active()) {
            return $query;
        }

        if ($orgColumn) {
            $query->where($orgColumn, self::orgId() ?? 0);
        } elseif ($branchColumn) {
            $query->whereIn($branchColumn, self::branchIds() ?: [0]);
        }

        if ($branchColumn && self::branchId()) {
            $query->where($branchColumn, self::branchId());
        }

        return $query;
    }

    /**
     * يملأ/يتحقق من الصيدلية والفرع عند حفظ سجل (لا يمكن الكتابة في صيدلية أو فرع آخر)
     */
    public static function fill($model, ?string $orgColumn, ?string $branchColumn, bool $branchRequired = false): void
    {
        if (! self::active()) {
            return;
        }

        if ($orgColumn) {
            $model->{$orgColumn} = self::orgId();
        }

        if ($branchColumn) {
            $value = $model->{$branchColumn};

            if (self::branchId()) {
                $model->{$branchColumn} = self::branchId();
            } elseif ($value) {
                if (! in_array((int) $value, self::branchIds(), true)) {
                    throw ValidationException::withMessages([$branchColumn => 'الفرع المختار لا يتبع صيدليتك.']);
                }
            } elseif ($branchRequired) {
                $model->{$branchColumn} = self::branchIds()[0] ?? null;
            }
        }
    }

    /**
     * الفرع الافتراضي للعمليات (البيع، الجرد...): فرع المستخدم، وإلا أول فرع في صيدليته.
     * الرجوع لأول فرع في النظام مسموح لمدير النظام وعمليات النظام الداخلية فقط،
     * حتى لا تُسجَّل حركة مستخدم صيدلية في فرع صيدلية أخرى.
     *
     * @throws ValidationException إذا كان المستخدم تابعاً لصيدلية ليس لها فروع
     */
    public static function defaultBranchId(): ?int
    {
        $own = self::branchId() ?? (self::branchIds()[0] ?? null);
        if ($own !== null) {
            return $own;
        }

        if (self::active()) {
            throw ValidationException::withMessages([
                'branch_id' => 'لا يوجد فرع مرتبط بحسابك أو بصيدليتك. أضف فرعاً للصيدلية أولاً.',
            ]);
        }

        $first = Branch::withoutGlobalScope('tenant')->value('id');

        return $first ? (int) $first : null;
    }

    public static function describe(): string
    {
        if (! self::user()) {
            return '';
        }
        if (self::isSuperAdmin()) {
            return 'مدير النظام';
        }
        $branch = self::branchId() ? Branch::withoutGlobalScopes()->whereKey(self::branchId())->value('name') : null;
        $org = self::orgId() ? \App\Models\Organization::withoutGlobalScopes()->whereKey(self::orgId())->value('name') : null;

        return trim(($org ?? '').($branch ? ' — '.$branch : ''), ' —');
    }
}
