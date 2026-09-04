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
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // 3. إنشاء الأدوار وتعيين الصلاحيات لها

        // دور مدير النظام (Super Admin) - يحصل على جميع الصلاحيات
        $adminRole = Role::findOrCreate('Admin', 'web');
        $adminRole->givePermissionTo(Permission::all());

        // دور الصيدلي (Pharmacist)
        $pharmacistRole = Role::findOrCreate('Pharmacist', 'web');
        $pharmacistRole->givePermissionTo([
            'view_dashboard',
            'view_customers',
            'create_customers',
            'view_pharmacy',
            'create_products',
            'edit_products',
        ]);

        // دور المحاسب (Accountant)
        $accountantRole = Role::findOrCreate('Accountant', 'web');
        $accountantRole->givePermissionTo([
            'view_dashboard',
            'view_purchases',
            'create_purchases',
            'view_customers',
        ]);

        // 4. إنشاء مستخدم تجريبي وإعطاؤه دور "Admin"
        $adminUser = User::firstOrCreate(
            ['username' => 'youssef'],
            [
                'organization_id' => 1,
                'email'           => 'yousefhassandouhan09@gmail.com',
                'phone'           => '0592157706',
                'password'        => bcrypt('password'),
                'is_active'       => true,
            ]
        );

        $adminUser->assignRole($adminRole);

        $adminUser->assignRole($adminRole);
    }
}
