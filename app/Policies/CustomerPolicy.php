<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAny(['view_customers', 'manage_sales']);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->allows($user, $customer, 'view_customers', 'manage_sales');
    }

    // الكاشير يحتاج إضافة عميل سريع من نقطة البيع
    public function create(User $user): bool
    {
        return $user->canAny(['create_customers', 'manage_sales']);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->allows($user, $customer, 'edit_customers');
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $this->allows($user, $customer, 'delete_customers');
    }

    // تسجيل دفعة سداد من العميل
    public function recordPayment(User $user, Customer $customer): bool
    {
        return $this->allows($user, $customer, 'manage_sales', 'edit_customers');
    }

    // إضافة دين يدوي (بدون فاتورة) عملية حساسة
    public function addDebt(User $user, Customer $customer): bool
    {
        return $this->allows($user, $customer, 'edit_customers');
    }
}
