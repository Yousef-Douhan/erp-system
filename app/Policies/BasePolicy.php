<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * أساس مشترك للسياسات:
 * - مدير النظام (Admin) يمر من Gate::before في AuthServiceProvider
 * - لا يجوز لمستخدم التعامل مع سجل تابع لمنظمة أخرى
 */
abstract class BasePolicy
{
    protected function sameOrganization(User $user, Model $model): bool
    {
        $userOrg = $user->organization_id;
        $modelOrg = $model->getAttribute('organization_id');

        // مستخدم بدون منظمة محددة، أو سجل بدون منظمة: لا يوجد ما نقارن به
        if (!$userOrg || !$modelOrg) {
            return true;
        }

        return (int) $userOrg === (int) $modelOrg;
    }

    protected function allows(User $user, Model $model, string ...$permissions): bool
    {
        return $this->sameOrganization($user, $model) && $user->canAny($permissions);
    }
}
