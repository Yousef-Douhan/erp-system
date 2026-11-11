<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage_users');
    }

    public function create(User $user): bool
    {
        return $user->can('manage_users');
    }

    public function update(User $user, User $target): bool
    {
        if (!$user->can('manage_users') || !$this->sameOrganization($user, $target)) {
            return false;
        }

        // غير مدير النظام: نفس الصيدلية، ومدير الفرع لموظفي فرعه فقط
        if (!$user->hasRole('Admin')) {
            if (!$user->organization_id || (int) $target->organization_id !== (int) $user->organization_id) {
                return false;
            }
            if ($user->branch_id && (int) $target->branch_id !== (int) $user->branch_id) {
                return false;
            }
        }

        // فقط مدير النظام يعدّل حساب مدير نظام آخر
        return !$target->hasRole('Admin') || $user->hasRole('Admin');
    }

    public function delete(User $user, User $target): bool
    {
        // لا أحد يحذف حسابه بنفسه من هنا
        return $user->id !== $target->id && $this->update($user, $target);
    }
}
