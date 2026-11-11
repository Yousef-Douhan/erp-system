<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * تحويل النظام إلى: عدة صيدليات ← لكل صيدلية فروع ← لكل فرع موظفون ومخزون وعملاء.
 *
 * 1) أعمدة جديدة: فرع المستخدم، فرع العميل، فرع المرتجع والجرد
 * 2) جدول أرصدة الفروع (branch_stocks)
 * 3) الأرقام الفريدة تصبح فريدة داخل الصيدلية فقط (الباركود، رقم فاتورة الشراء، رمز الحساب)
 * 4) نقل البيانات الحالية: كل صيدلية لها فرع ومستودع، والأرصدة الحالية توزَّع على الفروع حسب الدفعات
 * 5) أدوار جاهزة: صاحب صيدلية، مدير فرع، كاشير
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---------- 1) الأعمدة ----------
        foreach (['users', 'customers', 'sale_returns', 'stock_counts'] as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'branch_id')) {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    $col = $t->foreignId('branch_id')->nullable();
                    if ($table === 'users') {
                        $col->after('organization_id');
                    }
                    $col->constrained('branches')->nullOnDelete();
                });
            }
        }

        // ---------- 2) أرصدة الفروع ----------
        if (!Schema::hasTable('branch_stocks')) {
            Schema::create('branch_stocks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->integer('quantity')->default(0);
                $table->timestamps();
                $table->unique(['branch_id', 'product_id']);
            });
        }

        // ---------- 3) الفريد داخل الصيدلية ----------
        $this->swapUnique('products', 'products_barcode_unique', ['organization_id', 'barcode'], 'products_org_barcode_unique');
        $this->swapUnique('purchases', 'purchases_invoice_number_unique', ['organization_id', 'invoice_number'], 'purchases_org_invoice_unique');
        $this->swapUnique('accounts', 'accounts_account_code_unique', ['organization_id', 'account_code'], 'accounts_org_code_unique');
        // باركود الوحدات: يُتحقق منه داخل الصيدلية من التطبيق
        $this->swapUnique('product_units', 'product_units_barcode_unique', ['barcode'], 'product_units_barcode_index', false);
        $this->swapUnique('product_barcodes', 'product_barcodes_barcode_unique', ['barcode'], 'product_barcodes_barcode_index', false);

        // ---------- 4) نقل البيانات ----------
        $this->migrateData();

        // ---------- 5) الأدوار ----------
        $this->createRoles();
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_stocks');
        foreach (['users', 'customers', 'sale_returns', 'stock_counts'] as $table) {
            if (Schema::hasColumn($table, 'branch_id')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropConstrainedForeignId('branch_id'));
            }
        }
    }

    private function migrateData(): void
    {
        $now = now();
        $firstOrg = DB::table('organizations')->orderBy('id')->value('id');
        if (!$firstOrg) {
            return;
        }

        // كل صيدلية لها فرع واحد على الأقل
        foreach (DB::table('organizations')->pluck('id') as $orgId) {
            if (!DB::table('branches')->where('organization_id', $orgId)->exists()) {
                DB::table('branches')->insert([
                    'organization_id' => $orgId, 'name' => 'الفرع الرئيسي', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        $firstBranchOf = fn ($orgId) => DB::table('branches')->where('organization_id', $orgId)->orderBy('id')->value('id');
        $firstBranch = $firstBranchOf($firstOrg);

        // المستودعات بلا فرع تتبع أول فرع، وكل فرع له مستودع
        DB::table('warehouses')->whereNull('branch_id')->update(['branch_id' => $firstBranch]);
        foreach (DB::table('branches')->get(['id', 'name']) as $branch) {
            if (!DB::table('warehouses')->where('branch_id', $branch->id)->exists()) {
                DB::table('warehouses')->insert(['name' => 'مستودع ' . $branch->name, 'branch_id' => $branch->id, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        $warehouseOfBranch = fn ($branchId) => DB::table('warehouses')->where('branch_id', $branchId)->orderBy('id')->value('id');

        // الدفعات والحركات بلا مستودع، أو في مستودع صيدلية أخرى → مستودع أول فرع في صيدلية الصنف
        $productOrg = DB::table('products')->pluck('organization_id', 'id');
        $warehouseOrg = DB::table('warehouses')->join('branches', 'branches.id', '=', 'warehouses.branch_id')
            ->pluck('branches.organization_id', 'warehouses.id');

        foreach (DB::table('product_batches')->get(['id', 'product_id', 'warehouse_id']) as $b) {
            $org = $productOrg[$b->product_id] ?? $firstOrg;
            if (!$b->warehouse_id || (int) ($warehouseOrg[$b->warehouse_id] ?? 0) !== (int) $org) {
                DB::table('product_batches')->where('id', $b->id)->update(['warehouse_id' => $warehouseOfBranch($firstBranchOf($org) ?? $firstBranch)]);
            }
        }

        // أرصدة الفروع: كميات الدفعات في مستودعات كل فرع، والباقي (رصيد بلا دفعات) لأول فرع في الصيدلية
        DB::table('branch_stocks')->delete();
        $batchByBranch = DB::table('product_batches')
            ->join('warehouses', 'warehouses.id', '=', 'product_batches.warehouse_id')
            ->where('product_batches.quantity', '>', 0)
            ->groupBy('product_batches.product_id', 'warehouses.branch_id')
            ->select('product_batches.product_id', 'warehouses.branch_id', DB::raw('SUM(product_batches.quantity) as qty'))
            ->get()
            ->groupBy('product_id');

        foreach (DB::table('products')->get(['id', 'organization_id', 'stock_quantity']) as $p) {
            $rows = [];
            $batchTotal = 0;
            foreach ($batchByBranch[$p->id] ?? [] as $r) {
                $rows[(int) $r->branch_id] = (int) $r->qty;
                $batchTotal += (int) $r->qty;
            }

            $rest = (int) $p->stock_quantity - $batchTotal;
            if ($rest > 0) {
                $home = (int) ($firstBranchOf($p->organization_id) ?? $firstBranch);
                $rows[$home] = ($rows[$home] ?? 0) + $rest;
            }

            foreach ($rows as $branchId => $qty) {
                DB::table('branch_stocks')->insert(['branch_id' => $branchId, 'product_id' => $p->id, 'quantity' => $qty, 'created_at' => $now, 'updated_at' => $now]);
            }

            // الرصيد العام = مجموع الفروع (يصحح أي تعارض قديم بين الرصيد والدفعات)
            $sum = array_sum($rows);
            if ($sum !== (int) $p->stock_quantity) {
                DB::table('products')->where('id', $p->id)->update(['stock_quantity' => $sum]);
            }
        }

        // العملاء الحاليون يتبعون أول فرع في صيدليتهم
        foreach (DB::table('customers')->whereNull('branch_id')->get(['id', 'organization_id']) as $c) {
            DB::table('customers')->where('id', $c->id)->update(['branch_id' => $firstBranchOf($c->organization_id ?? $firstOrg) ?? $firstBranch]);
        }

        // المرتجع يأخذ فرع فاتورته، والجرد أول فرع في الصيدلية
        if (Schema::hasTable('sale_returns')) {
            DB::statement('UPDATE sale_returns r JOIN sales s ON s.id = r.sale_id SET r.branch_id = s.branch_id WHERE r.branch_id IS NULL');
        }
        if (Schema::hasTable('stock_counts')) {
            foreach (DB::table('stock_counts')->whereNull('branch_id')->get(['id', 'organization_id']) as $sc) {
                DB::table('stock_counts')->where('id', $sc->id)->update(['branch_id' => $firstBranchOf($sc->organization_id ?? $firstOrg) ?? $firstBranch]);
            }
        }

        // كل جدول له صيدلية: ما كان فارغاً يتبع أول صيدلية
        foreach (['products', 'customers', 'suppliers', 'categories', 'units', 'brands', 'manufacturers', 'accounts', 'journal_entries', 'sales', 'purchases', 'employees'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'organization_id')) {
                DB::table($table)->whereNull('organization_id')->update(['organization_id' => $firstOrg]);
            }
        }

        // المستخدمون (غير مدير النظام): صيدلية إلزامية، والفرع من ملف الموظف إن وُجد
        $adminIds = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'Admin')
            ->pluck('model_has_roles.model_id');

        DB::table('users')->whereNotIn('id', $adminIds)->whereNull('organization_id')->update(['organization_id' => $firstOrg]);

        if (Schema::hasTable('employees')) {
            $employeeBranch = DB::table('employees')->whereNotNull('user_id')->whereNotNull('branch_id')->pluck('branch_id', 'user_id');
            foreach ($employeeBranch as $userId => $branchId) {
                if (!$adminIds->contains($userId)) {
                    DB::table('users')->where('id', $userId)->whereNull('branch_id')->update(['branch_id' => $branchId]);
                }
            }
        }
    }

    private function createRoles(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        \App\Support\PermissionCatalog::ensureExists();

        $all = Permission::where('guard_name', 'web')->pluck('name');
        $roles = [
            // صاحب الصيدلية: كل شيء داخل صيدليته، ما عدا إعدادات النظام العامة
            \App\Support\PermissionCatalog::OWNER_ROLE => $all->diff(['manage_roles', 'manage_backups', 'view_audit_logs'])->values()->all(),

            // مدير الفرع: إدارة فرعه وموظفيه بدون تعديل كتالوج الأصناف أو حذف الفواتير
            \App\Support\PermissionCatalog::BRANCH_MANAGER_ROLE => [
                'view_dashboard', 'view_reports',
                'manage_sales', 'edit_sales', 'return_sales', 'manage_shifts',
                'view_customers', 'create_customers', 'edit_customers',
                'view_pharmacy', 'stock_count', 'print_barcodes', 'transfer_stock',
                'view_purchases', 'create_purchases', 'pay_suppliers',
                'manage_users',
            ],

            // الكاشير: البيع والوردية والمرتجعات والعملاء
            \App\Support\PermissionCatalog::CASHIER_ROLE => [
                'manage_sales', 'return_sales', 'manage_shifts', 'view_customers', 'create_customers',
            ],
        ];

        foreach ($roles as $name => $permissions) {
            $exists = Role::where('name', $name)->where('guard_name', 'web')->exists();
            $role = Role::findOrCreate($name, 'web');
            if (!$exists) {
                $role->syncPermissions(array_values(array_intersect($permissions, $all->all())));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * استبدال فهرس فريد بآخر (مع التحقق من وجود الفهارس)
     */
    private function swapUnique(string $table, string $old, array $columns, string $new, bool $unique = true): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumns($table, $columns)) {
            return;
        }

        if ($this->indexExists($table, $old)) {
            Schema::table($table, fn (Blueprint $t) => $t->dropUnique($old));
        }
        if (!$this->indexExists($table, $new)) {
            Schema::table($table, fn (Blueprint $t) => $unique ? $t->unique($columns, $new) : $t->index($columns, $new));
        }
    }

    private function indexExists(string $table, string $name): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $name)
            ->exists();
    }
};
