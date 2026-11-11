<?php

namespace App\Policies;

use App\Models\Purchase;
use App\Models\User;

class PurchasePolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_purchases');
    }

    public function view(User $user, Purchase $purchase): bool
    {
        return $this->allows($user, $purchase, 'view_purchases');
    }

    public function create(User $user): bool
    {
        return $user->can('create_purchases');
    }

    public function update(User $user, Purchase $purchase): bool
    {
        return $this->allows($user, $purchase, 'edit_purchases');
    }

    public function delete(User $user, Purchase $purchase): bool
    {
        return $this->allows($user, $purchase, 'delete_purchases');
    }

    public function pay(User $user, Purchase $purchase): bool
    {
        return $this->allows($user, $purchase, 'pay_suppliers');
    }
}
