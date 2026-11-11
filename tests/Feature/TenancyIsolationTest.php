<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\Stock;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * يتأكد أن الصيدليات والفروع لا ترى بيانات بعضها.
 * يعمل على قاعدة erp_system_testing (أنشئها فارغة أولاً): php artisan test --filter=TenancyIsolationTest
 */
class TenancyIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private Organization $orgB;
    private Branch $a1;
    private Branch $a2;
    private Branch $b1;
    private Product $productA;
    private Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orgA = Organization::create(['name' => 'صيدلية أ', 'currency' => 'ILS', 'timezone' => 'Asia/Hebron', 'status' => 'active']);
        $this->orgB = Organization::create(['name' => 'صيدلية ب', 'currency' => 'ILS', 'timezone' => 'Asia/Hebron', 'status' => 'active']);
        $this->a1 = Branch::create(['organization_id' => $this->orgA->id, 'name' => 'فرع أ1', 'status' => 'active']);
        $this->a2 = Branch::create(['organization_id' => $this->orgA->id, 'name' => 'فرع أ2', 'status' => 'active']);
        $this->b1 = Branch::create(['organization_id' => $this->orgB->id, 'name' => 'فرع ب1', 'status' => 'active']);

        $this->productA = Product::create(['organization_id' => $this->orgA->id, 'name' => 'دواء صيدلية أ', 'purchase_price' => 1, 'selling_price' => 2, 'stock_quantity' => 0, 'alert_quantity' => 1]);
        $this->productB = Product::create(['organization_id' => $this->orgB->id, 'name' => 'دواء صيدلية ب', 'purchase_price' => 1, 'selling_price' => 2, 'stock_quantity' => 0, 'alert_quantity' => 1]);
    }

    private function user(string $role, Organization $org, ?Branch $branch = null): User
    {
        $u = User::create([
            'organization_id' => $org->id,
            'branch_id'       => $branch?->id,
            'username'        => 'u' . uniqid(),
            'email'           => uniqid() . '@test.local',
            'password'        => 'secret123',
            'is_active'       => true,
        ]);
        $u->assignRole($role);
        Tenancy::flush();

        return $u;
    }

    public function test_pharmacy_sees_only_its_products(): void
    {
        $this->actingAs($this->user(PermissionCatalog::OWNER_ROLE, $this->orgA));

        $this->assertSame([$this->productA->id], Product::pluck('id')->all());
        $this->assertNull(Product::find($this->productB->id));
    }

    public function test_owner_sees_all_his_branches_only(): void
    {
        $this->actingAs($this->user(PermissionCatalog::OWNER_ROLE, $this->orgA));

        $this->assertEqualsCanonicalizing([$this->a1->id, $this->a2->id], Branch::pluck('id')->all());
    }

    public function test_cashier_sees_only_his_branch(): void
    {
        $this->actingAs($this->user(PermissionCatalog::CASHIER_ROLE, $this->orgA, $this->a1));

        $this->assertSame([$this->a1->id], Branch::pluck('id')->all());
    }

    public function test_new_records_are_forced_into_users_branch(): void
    {
        $this->actingAs($this->user(PermissionCatalog::CASHIER_ROLE, $this->orgA, $this->a1));

        // يحاول الكتابة في صيدلية وفرع آخرين
        $c = Customer::create(['name' => 'عميل', 'organization_id' => $this->orgB->id, 'branch_id' => $this->b1->id]);

        $this->assertSame($this->orgA->id, (int) $c->organization_id);
        $this->assertSame($this->a1->id, (int) $c->branch_id);
    }

    public function test_stock_is_per_branch(): void
    {
        Stock::move($this->productA->id, $this->a1->id, 10, 'stock_in');

        $this->assertSame(10, Stock::quantity($this->productA->id, $this->a1->id));
        $this->assertSame(0, Stock::quantity($this->productA->id, $this->a2->id));
        $this->assertSame(10, (int) $this->productA->fresh()->stock_quantity);
    }

    public function test_products_page_hides_other_pharmacy(): void
    {
        $this->actingAs($this->user(PermissionCatalog::OWNER_ROLE, $this->orgA));

        $this->get('/products')->assertOk()->assertSee('دواء صيدلية أ')->assertDontSee('دواء صيدلية ب');
    }

    public function test_cannot_open_other_pharmacy_record_by_url(): void
    {
        $this->actingAs($this->user(PermissionCatalog::OWNER_ROLE, $this->orgA));

        $customerB = Tenancy::bypass(fn () => Customer::create(['name' => 'عميل ب', 'organization_id' => $this->orgB->id, 'branch_id' => $this->b1->id]));

        $this->get('/customers/' . $customerB->id . '/ledger')->assertNotFound();
    }
}
