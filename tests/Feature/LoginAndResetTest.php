<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * صفحة الدخول (باسم المستخدم أو البريد) واستعادة كلمة المرور بالعربي.
 * php artisan test --filter=LoginAndResetTest
 */
class LoginAndResetTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $extra = []): User
    {
        return User::factory()->create(array_merge(['username' => 'cashier' . uniqid(), 'password' => 'secret123', 'is_active' => true], $extra));
    }

    public function test_pages_render_in_arabic(): void
    {
        $this->get('/login')->assertOk()->assertSee('تسجيل الدخول')->assertSee('نسيت كلمة المرور؟');
        $this->get('/forgot-password')->assertOk()->assertSee('أرسل رابط الاستعادة');
        $this->get('/reset-password/abc?email=a@b.c')->assertOk()->assertSee('كلمة مرور جديدة');
    }

    public function test_login_with_username(): void
    {
        $user = $this->user();
        $this->post('/login', ['email' => $user->username, 'password' => 'secret123'])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_with_email_still_works_and_wrong_password_is_arabic(): void
    {
        $user = $this->user();
        $this->post('/login', ['email' => $user->email, 'password' => 'nope'])->assertSessionHasErrors(['email' => 'اسم المستخدم/البريد أو كلمة المرور غير صحيحة.']);
        $this->assertGuest();

        $this->post('/login', ['email' => $user->email, 'password' => 'secret123'])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_unknown_email_gets_the_same_message_and_no_mail(): void
    {
        Notification::fake();
        $user = $this->user();

        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHasNoErrors();
        $knownMsg = session('status');
        $this->flushSession();
        $this->post('/forgot-password', ['email' => 'nobody@nowhere.test'])->assertSessionHasNoErrors();
        $unknownMsg = session('status');

        $this->assertSame($knownMsg, $unknownMsg, 'الرسالة تكشف إن كان البريد مسجلاً');
        $this->assertStringContainsString('إذا كان هذا البريد مسجلاً', $unknownMsg);

        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertCount(1);
    }

    public function test_inactive_user_does_not_get_a_link(): void
    {
        Notification::fake();
        $user = $this->user(['is_active' => false]);

        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHasNoErrors();
        Notification::assertNothingSent();
    }

    public function test_reset_mail_is_arabic_and_new_password_works(): void
    {
        Notification::fake();
        $user = $this->user();
        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($n) use ($user) {
            $mail = $n->toMail($user);
            $this->assertStringContainsString('استعادة كلمة المرور', $mail->subject);
            $this->assertSame('تعيين كلمة مرور جديدة', $mail->actionText);

            $this->post('/reset-password', [
                'token' => $n->token, 'email' => $user->email, 'password' => 'NewPass123!', 'password_confirmation' => 'NewPass123!',
            ])->assertRedirect(route('login'))->assertSessionHas('status');

            return true;
        });

        $this->post('/login', ['email' => $user->username, 'password' => 'NewPass123!'])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_bad_token_shows_arabic_error(): void
    {
        $user = $this->user();
        $this->post('/reset-password', [
            'token' => 'wrong', 'email' => $user->email, 'password' => 'NewPass123!', 'password_confirmation' => 'NewPass123!',
        ])->assertSessionHasErrors(['email' => 'رابط الاستعادة غير صالح أو انتهت صلاحيته. اطلب رابطاً جديداً.']);
    }
}
