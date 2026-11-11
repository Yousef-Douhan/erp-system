<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * الصفحات المحمية يجب أن تحوّل الزائر غير المسجّل إلى صفحة الدخول.
 */
class AccessControlTest extends TestCase
{
    public static function protectedPages(): array
    {
        return [
            'لوحة التحكم' => ['/dashboard'],
            'نقطة البيع'  => ['/pos'],
            'الفواتير'    => ['/sales'],
            'العملاء'     => ['/customers'],
            'المستخدمون'  => ['/users'],
            'الأدوار'     => ['/roles'],
        ];
    }

    /** @dataProvider protectedPages */
    public function test_guest_is_redirected_to_login(string $url): void
    {
        $this->get($url)->assertRedirect('/login');
    }

    public function test_guest_cannot_create_sale(): void
    {
        $this->post('/sales', [])->assertRedirect('/login');
    }
}
