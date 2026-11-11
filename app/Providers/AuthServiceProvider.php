<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        \App\Models\Sale::class     => \App\Policies\SalePolicy::class,
        \App\Models\Customer::class => \App\Policies\CustomerPolicy::class,
        \App\Models\Product::class  => \App\Policies\ProductPolicy::class,
        \App\Models\Purchase::class => \App\Policies\PurchasePolicy::class,
        \App\Models\User::class     => \App\Policies\UserPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // مدير النظام (Admin) يملك كل الصلاحيات تلقائياً
        Gate::before(function ($user, $ability) {
            return method_exists($user, 'hasRole') && $user->hasRole('Admin') ? true : null;
        });
    }
}
