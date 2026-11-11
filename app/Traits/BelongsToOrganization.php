<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * حصر القوائم في منظمة المستخدم الحالي.
 * الاستخدام: Model::visibleTo(auth()->user())->...
 * مدير النظام، أو المستخدم بلا منظمة، يرى كل السجلات.
 */
trait BelongsToOrganization
{
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (!$user || !$user->organization_id || $user->hasRole('Admin')) {
            return $query;
        }

        return $query->where($this->getTable() . '.organization_id', $user->organization_id);
    }
}
