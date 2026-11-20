<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Branch;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\StockCount;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\BuildsPharmacy;
use Tests\TestCase;

/**
 * يفتح كل صفحات الموقع بكل الأدوار:
 *  - مدير النظام: كل صفحة يجب أن تفتح (200) أو تحوّل (3xx)، بدون أي خطأ 500.
 *  - صاحب الصيدلية / مدير الفرع / الكاشير: الصفحة بدون صلاحيتها مرفوضة (403/404 أو تحويل مع رسالة)، والمسموحة لا تعطي 500.
 *
 * php artisan test --filter=PagesSmokeTest
 */
class PagesSmokeTest extends TestCase
{
    use RefreshDatabase;
    use BuildsPharmacy;

    private Organization $org;
    private Branch $branch;
    private Product $product;
    private Customer $customer;
    private Sale $sale;
    private User $cashier;
    private ?SaleReturn $return = null;
    private ?CashShift $shift = null;
    private ?StockCount $count = null;
    private int $supplierId = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = $this->makeOrg();
        $this->branch = $this->makeBranch($this->org);
        $this->product = $this->makeProduct($this->org, 10, 6);
        $this->stockIn($this->product, $this->branch, 50);
        $this->customer = $this->makeCustomer($this->org, $this->branch);
        $this->supplierId = $this->makeSupplier($this->org)->id;
        $register = $this->makeRegister($this->branch, 200);

        $this->cashier = $this->makeCashier($this->org, $this->branch);
        $this->sale = $this->sellViaPos($this->cashier, [[$this->product, 3]], 30);

        // بيانات للصفحات التفصيلية: مرتجع، وردية، جرد
        $owner = $this->makeOwner($this->org);
        $this->actingAs($owner)->post("/sales/{$this->sale->id}/return", [
            'quantities' => [$this->sale->items()->first()->id => 1],
            'restock'    => 1,
        ]);
        $this->actingAs($this->cashier)->post('/shifts', ['cash_register_id' => $register->id]);
        $this->actingAs($owner)->post('/stock-counts', ['branch_id' => $this->branch->id]);

        Tenancy::flush();
        Tenancy::bypass(function () {
            $this->return = SaleReturn::latest('id')->first();
            $this->shift = CashShift::latest('id')->first();
            $this->count = StockCount::latest('id')->first();
        });
    }

    /**
     * [الرابط, الصلاحية المطلوبة (أي واحدة من المفصولة بـ |) أو null لأي مستخدم مسجّل]
     */
    private function pages(): array
    {
        $p = $this->product->id;
        $s = $this->sale->id;
        $roleId = Role::where('name', PermissionCatalog::CASHIER_ROLE)->value('id');
        $accountId = Tenancy::bypass(fn () => Account::value('id'));

        $pages = [
            ['/dashboard', null],
            ['/profile', null],
            ['/notifications', null],
            ['/analytics', 'view_dashboard'],

            ['/roles', 'manage_roles'],
            ['/roles/create', 'manage_roles'],
            ["/roles/{$roleId}/edit", 'manage_roles'],
            ['/users', 'manage_users'],

            ['/organizations', 'manage_settings'],
            ['/branches', 'manage_settings'],
            ['/payment-methods', 'manage_settings'],

            ['/products', 'view_pharmacy'],
            ["/products/{$p}/units", 'view_pharmacy'],
            ['/products/import', 'view_pharmacy'],
            ['/products/import/template', 'view_pharmacy'],
            ['/inventory-transactions', 'view_pharmacy'],
            ['/categories', 'view_pharmacy'],
            ['/brands', 'view_pharmacy'],
            ['/units', 'view_pharmacy'],
            ['/prescriptions', 'view_pharmacy'],
            ['/drug-alerts', 'view_pharmacy'],
            ['/batches', 'view_pharmacy'],
            ['/batches?status=expired', 'view_pharmacy'],
            ['/batches?status=expiring', 'view_pharmacy'],
            ['/disposals', 'stock_count'],

            ['/pos', 'manage_sales'],
            ['/sales', 'manage_sales'],
            ["/sales/{$s}", 'manage_sales'],
            ["/sales/{$s}/receipt", 'manage_sales'],

            ['/customers', 'view_customers|manage_sales'],
            ['/debts', 'view_customers|manage_sales'],
            ["/customers/{$this->customer->id}/ledger", 'view_customers|manage_sales'],

            ['/suppliers', 'view_purchases'],
            ['/purchases', 'view_purchases'],
            ['/supplier-payments', 'pay_suppliers'],
            ["/suppliers/{$this->supplierId}/statement", 'view_purchases'],
            ['/reorder', 'view_purchases'],
            ['/purchase-returns', 'view_purchases'],

            ['/accounts', 'manage_accounting'],
            ['/journal-entries', 'manage_accounting'],
            ['/cash-registers', 'manage_accounting'],
            ['/transfers', 'verify_transfers'],
            ['/transfers?status=pending&date=2026-01-01', 'verify_transfers'],
            ['/expenses', 'manage_accounting'],
            ['/reports/income-statement', 'manage_accounting'],
            ['/reports/balance-sheet', 'manage_accounting'],

            ['/returns', 'return_sales'],
            ["/sales/{$s}/return", 'return_sales'],

            ['/shifts', 'manage_shifts|manage_accounting'],
            ['/stock-counts', 'stock_count'],
            ['/warehouses', 'transfer_stock'],
            ['/stock-transfers', 'transfer_stock'],
            ['/barcodes', 'print_barcodes'],

            ['/reports/sales', 'view_reports'],
            ['/reports/sales?group=month', 'view_reports'],
            ['/reports/profit', 'view_reports'],
            ['/reports/profit?export=csv', 'view_reports'],
            ['/reports/employees', 'view_reports'],
            ['/reports/slow-moving', 'view_reports'],

            ['/backups', 'manage_backups'],
            ['/trash', 'delete_products|delete_customers|delete_purchases|manage_users|manage_settings|manage_accounting'],
            ['/audit-logs', 'view_audit_logs'],
        ];

        $registerId = Tenancy::bypass(fn () => \App\Models\CashRegister::value('id'));
        if ($registerId) {
            $pages[] = ["/cash-registers/{$registerId}", 'manage_accounting'];
        }
        if ($accountId) {
            $pages[] = ["/accounts/{$accountId}", 'manage_accounting'];
        }
        if ($this->return) {
            $pages[] = ["/returns/{$this->return->id}", 'return_sales'];
        }
        if ($this->shift) {
            $pages[] = ["/shifts/{$this->shift->id}", 'manage_shifts|manage_accounting'];
        }
        if ($this->count) {
            $pages[] = ["/stock-counts/{$this->count->id}", 'stock_count'];
        }

        return $pages;
    }

    public function test_setup_created_detail_records(): void
    {
        $this->assertNotNull($this->return, 'لم يُنشأ المرتجع في التجهيز');
        $this->assertNotNull($this->shift, 'لم تُفتح الوردية في التجهيز');
        $this->assertNotNull($this->count, 'لم يُنشأ الجرد في التجهيز');
    }

    public function test_admin_can_open_every_page(): void
    {
        $admin = $this->makeAdmin();
        $failures = [];

        foreach ($this->pages() as [$url]) {
            Tenancy::flush();
            $this->flushSession();
            $status = $this->actingAs($admin)->get($url)->getStatusCode();
            if ($status >= 400 || ($status === 302 && session()->has('error'))) {
                $failures[] = "{$url} => {$status} " . (session('error') ?? '');
            }
        }

        $this->assertSame([], $failures, "صفحات لم تفتح لمدير النظام:\n" . implode("\n", $failures));
    }

    public function test_owner_pages(): void
    {
        $this->checkRole($this->makeOwner($this->org), 'صاحب الصيدلية');
    }

    public function test_branch_manager_pages(): void
    {
        $this->checkRole($this->makeManager($this->org, $this->branch), 'مدير الفرع');
    }

    public function test_cashier_pages(): void
    {
        $this->checkRole($this->cashier, 'الكاشير');
    }

    private function checkRole(User $user, string $label): void
    {
        $failures = [];

        foreach ($this->pages() as [$url, $permission]) {
            Tenancy::flush();
            $allowed = $permission === null || $user->canAny(explode('|', $permission));
            $this->flushSession();
            $response = $this->actingAs($user)->get($url);
            $status = $response->getStatusCode();

            if ($status >= 500) {
                $failures[] = "{$url} => {$status} (خطأ في السيرفر)";
            } elseif (!$allowed && !$this->isDenied($response)) {
                $failures[] = "{$url} => {$status} (المفروض ممنوع: لا يملك {$permission})";
            }
        }

        $this->assertSame([], $failures, "مشاكل في صفحات {$label}:\n" . implode("\n", $failures));
    }
}
