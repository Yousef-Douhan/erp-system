<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

class SalePolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage_sales');
    }

    public function view(User $user, Sale $sale): bool
    {
        return $this->allows($user, $sale, 'manage_sales');
    }

    public function create(User $user): bool
    {
        return $user->can('manage_sales');
    }

    /**
     * التعديل: صاحب صلاحية "تعديل الفواتير" يعدّل أي فاتورة،
     * والبائع العادي يعدّل فواتيره هو من اليوم نفسه فقط.
     */
    public function update(User $user, Sale $sale): bool
    {
        if (!$this->sameOrganization($user, $sale)) {
            return false;
        }

        if ($user->can('edit_sales')) {
            return true;
        }

        return $user->can('manage_sales')
            && (int) $sale->employee_id === (int) $user->id
            && $sale->created_at?->isToday();
    }

    public function delete(User $user, Sale $sale): bool
    {
        return $this->allows($user, $sale, 'delete_sales');
    }

    public function pay(User $user, Sale $sale): bool
    {
        return $this->allows($user, $sale, 'manage_sales');
    }
}
