<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_pharmacy');
    }

    public function view(User $user, Product $product): bool
    {
        return $this->allows($user, $product, 'view_pharmacy');
    }

    public function create(User $user): bool
    {
        return $user->can('create_products');
    }

    public function update(User $user, Product $product): bool
    {
        return $this->allows($user, $product, 'edit_products');
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->allows($user, $product, 'delete_products');
    }
}
