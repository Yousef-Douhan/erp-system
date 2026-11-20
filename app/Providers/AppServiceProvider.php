<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;


use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // رسالة استعادة كلمة المرور بالعربي
        \Illuminate\Auth\Notifications\ResetPassword::toMailUsing(function ($user, string $token) {
            $url = url(route('password.reset', ['token' => $token, 'email' => $user->getEmailForPasswordReset()], false));
            $minutes = config('auth.passwords.' . config('auth.defaults.passwords') . '.expire', 60);
            $name = $user->full_name ?: $user->username;

            return (new \Illuminate\Notifications\Messages\MailMessage)
                ->subject('استعادة كلمة المرور — ' . config('app.name'))
                ->greeting('مرحباً ' . $name . '،')
                ->line('وصلنا طلب لتعيين كلمة مرور جديدة لحسابك (' . $user->username . ').')
                ->action('تعيين كلمة مرور جديدة', $url)
                ->line('الرابط صالح لمدة ' . $minutes . ' دقيقة ولمرة واحدة فقط.')
                ->line('إذا لم تطلب ذلك فتجاهل هذه الرسالة، وكلمة مرورك الحالية تبقى كما هي.')
                ->salutation('مع التحية، ' . config('app.name'));
        });

    }
}
