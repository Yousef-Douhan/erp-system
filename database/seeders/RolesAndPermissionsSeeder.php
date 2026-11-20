<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use App\Models\User;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // 1. إعادة إعادة تعيين ذاكرة التخزين المؤقت للصلاحيات (مهم جداً)
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. إنشاء قائمة الصلاحيات الخاصة بجميع الأقسام
        $permissions = [
            // لوحة التحكم
            'view_dashboard',
            
            // إدارة العملاء
            'view_customers',
            'create_customers',
            'edit_customers',
            'delete_customers',

            // إدارة الصيدلية والمخزون
            'view_pharmacy',
            'create_products',
            'edit_products',
            'delete_products',

            // إدارة المشتريات والموردين
            'view_purchases',
            'create_purchases',

            // إدارة الصلاحيات والمستخدمين
            'manage_roles',
            'manage_users',

            // المبيعات ونقطة البيع
            'manage_sales',
            'edit_sales',
            'delete_sales',

            // المشتريات (تعديل وحذف)
            'edit_purchases',
            'delete_purchases',

            // المحاسبة والخزائن والتقارير
            'manage_accounting',

            // إعدادات المنشأة (المنظمات، الفروع، الموظفين، طرق الدفع)
            'manage_settings',

            // سجل التدقيق
            'view_audit_logs',

            // المرتجعات والورديات والجرد ودفعات الموردين والتقارير
            'return_sales',
            'manage_shifts',
            'stock_count',
            'transfer_stock',
            'print_barcodes',
            'pay_suppliers',
            'view_reports',
            'manage_backups',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // 3. إنشاء الأدوار وتعيين الصلاحيات لها

        // دور مدير النظام (Super Admin) - يحصل على جميع الصلاحيات
        $adminRole = Role::findOrCreate('Admin', 'web');
        $adminRole->givePermissionTo(Permission::all());

        // أدوار الصيدليات (صاحب صيدلية / مدير فرع / كاشير) تُنشأ في ترحيل multi_pharmacy_branches
        // ويمكن إنشاء أدوار إضافية من شاشة «الأدوار والصلاحيات».

        // 4. حساب مدير النظام (يُنشأ مرة واحدة فقط ولا تُغيَّر كلمة سره إن كان موجوداً)
        // على السيرفر: كلمة السر من ADMIN_PASSWORD في .env، وإلا تُولَّد كلمة عشوائية قوية وتُطبع مرة واحدة.
        $password = env('ADMIN_PASSWORD');
        $generated = false;
        if (!$password) {
            if (app()->environment('production')) {
                $password = \Illuminate\Support\Str::password(16, symbols: false);
                $generated = true;
            } else {
                $password = 'password'; // للتطوير المحلي فقط
            }
        }

        $adminUser = User::firstOrCreate(
            ['username' => 'youssef'],
            [
                'organization_id' => \App\Models\Organization::whereKey(1)->exists() ? 1 : null,
                'email'           => 'yousefhassandouhan09@gmail.com',
                'phone'           => '0592157706',
                'password'        => bcrypt($password),
                'is_active'       => true,
            ]
        );

        $adminUser->assignRole($adminRole);

        if ($adminUser->wasRecentlyCreated && $generated && $this->command) {
            $this->command->warn('تم إنشاء حساب المدير: youssef / ' . $password . '  (احفظها الآن وغيّرها بعد أول دخول)');
        }
    }
}
