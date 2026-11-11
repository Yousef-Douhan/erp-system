<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

/**
 * التسجيل الذاتي مغلق افتراضياً: المستخدمون يُنشَؤون من شاشة المستخدمين فقط.
 * لإعادة فتحه ضع ALLOW_REGISTRATION=true في ملف .env
 */
class RegistrationTest extends TestCase
{
    public function test_registration_screen_is_closed(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_registration_post_is_closed(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password1',
            'password_confirmation' => 'password1',
        ])->assertNotFound();

        $this->assertGuest();
    }
}
