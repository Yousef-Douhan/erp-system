<?php

namespace Tests\Feature;

use App\Models\CustomerTransaction;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BuildsPharmacy;
use Tests\TestCase;

/**
 * بعد تنظيف الجداول المكررة: دفتر العميل جدول واحد، والاسم والراتب على المستخدم، والجداول المحذوفة غير موجودة.
 * php artisan test --filter=CleanupRegressionTest
 */
class CleanupRegressionTest extends TestCase
{
    use RefreshDatabase;
    use BuildsPharmacy;

    public function test_removed_tables_are_gone(): void
    {
        foreach (['customer_accounts', 'employee_branches', 'manufacturers', 'product_stock_settings', 'inventory_transaction_items'] as $table) {
            $this->assertFalse(Schema::hasTable($table), "الجدول {$table} ما زال موجوداً");
        }
        $this->assertTrue(Schema::hasColumns('users', ['full_name', 'salary']));
        $this->assertTrue(Schema::hasColumns('customer_transactions', ['source', 'balance_after']));
    }

    public function test_customer_debt_is_written_once_with_running_balance(): void
    {
        $org = $this->makeOrg();
        $branch = $this->makeBranch($org);
        $product = $this->makeProduct($org, 10, 6);
        $this->stockIn($product, $branch, 10);
        $this->makeRegister($branch);
        $customer = $this->makeCustomer($org, $branch);

        $this->sellViaPos($this->makeCashier($org, $branch), [[$product, 2]], 0, ['payment_method' => 'debt', 'customer_id' => $customer->id]);

        $rows = Tenancy::bypass(fn () => CustomerTransaction::where('customer_id', $customer->id)->get());
        $this->assertCount(1, $rows);
        $this->assertSame('add', $rows->first()->type);
        $this->assertSame('sale_invoice', $rows->first()->source);
        $this->assertEquals(20, (float) $rows->first()->balance_after);
    }

    public function test_owner_adds_staff_with_name_and_salary(): void
    {
        $org = $this->makeOrg();
        $branch = $this->makeBranch($org);
        $owner = $this->makeOwner($org);

        $this->actingAs($owner)->post('/users', [
            'username' => 'cashier' . uniqid(), 'email' => uniqid('c') . '@test.local',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
            'role' => PermissionCatalog::CASHIER_ROLE, 'branch_id' => $branch->id,
            'full_name' => 'أحمد خليل', 'salary' => 1500, 'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $user = User::where('full_name', 'أحمد خليل')->first();
        $this->assertNotNull($user);
        $this->assertEquals(1500, (float) $user->salary);
        $this->assertSame($org->id, (int) $user->organization_id);
    }
}
