<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * الصفحات الجديدة محمية: الزائر يُحوَّل لتسجيل الدخول
 */
class NewPagesAccessTest extends TestCase
{
    public static function pages(): array
    {
        return [
            'المرتجعات'      => ['/returns'],
            'الورديات'       => ['/shifts'],
            'الجرد'          => ['/stock-counts'],
            'المستودعات'     => ['/warehouses'],
            'نقل المخزون'    => ['/stock-transfers'],
            'الباركود'       => ['/barcodes'],
            'تقرير الأرباح'  => ['/reports/profit'],
            'الموظفون'       => ['/reports/employees'],
            'الراكدة'        => ['/reports/slow-moving'],
            'النسخ'          => ['/backups'],
            'التحليلات'      => ['/analytics'],
        ];
    }

    /** @dataProvider pages */
    public function test_guest_is_redirected(string $url): void
    {
        $this->get($url)->assertRedirect('/login');
    }

    public function test_guest_cannot_download_backup(): void
    {
        $this->get('/backups/erp-2026-01-01_000000.sql.gz')->assertRedirect('/login');
    }
}
