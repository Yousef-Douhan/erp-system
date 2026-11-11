<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * إنشاء الصلاحيات الجديدة وإعطاؤها تلقائياً للأدوار التي تعمل في القسم نفسه:
 * من يبيع يأخذ المرتجعات والوردية، ومن يدخل الصيدلية يأخذ الجرد والمستودعات والباركود، وهكذا.
 * المدير (Admin) يأخذ الكل. النسخ الاحتياطية للمدير فقط.
 */
return new class extends Migration
{
    private array $map = [
        // الصلاحية الجديدة   => تُعطى لكل دور يملك إحدى هذه الصلاحيات
        'return_sales'   => ['manage_sales'],
        'manage_shifts'  => ['manage_sales'],
        'stock_count'    => ['view_pharmacy'],
        'transfer_stock' => ['edit_products'],
        'print_barcodes' => ['view_pharmacy'],
        'pay_suppliers'  => ['create_purchases', 'manage_accounting'],
        'view_reports'   => ['manage_accounting'],
        'manage_backups' => [],
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (array_keys($this->map) as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (Role::where('guard_name', 'web')->with('permissions')->get() as $role) {
            $has = $role->permissions->pluck('name');

            $grant = $role->name === 'Admin'
                ? array_keys($this->map)
                : collect($this->map)->filter(fn ($needs) => $has->intersect($needs)->isNotEmpty())->keys()->all();

            if ($grant) {
                $role->givePermissionTo($grant);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // لا نحذف الصلاحيات حتى لا تضيع إعدادات الأدوار
    }
};
