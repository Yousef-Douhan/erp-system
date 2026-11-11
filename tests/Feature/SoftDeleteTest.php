<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * php artisan test --filter=SoftDeleteTest   (على قاعدة erp_system_testing)
 */
class SoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function pharmacy(string $tag): array
    {
        $tag .= uniqid(); // أسماء فريدة حتى لا تتعارض بيانات الاختبارات
        $org = Organization::create(['name' => "صيدلية {$tag}", 'currency' => 'ILS', 'timezone' => 'Asia/Hebron', 'status' => 'active']);
        $branch = Branch::create(['organization_id' => $org->id, 'name' => "فرع {$tag}", 'status' => 'active']);
        $owner = User::create(['organization_id' => $org->id, 'username' => "owner{$tag}", 'email' => "o{$tag}@t.local", 'password' => 'secret123', 'is_active' => true]);
        $owner->assignRole(PermissionCatalog::OWNER_ROLE);
        Tenancy::flush();

        return [$org, $branch, $owner];
    }

    public function test_deleted_customer_stays_in_database_and_can_be_restored(): void
    {
        [$org, $branch, $owner] = $this->pharmacy('A');
        $customer = Customer::withoutGlobalScopes()->create(['organization_id' => $org->id, 'branch_id' => $branch->id, 'name' => 'أبو أحمد', 'phone' => '0599000000']);

        $this->actingAs($owner)->delete("/customers/{$customer->id}")->assertRedirect('/customers');

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
        Tenancy::flush();

        $this->actingAs($owner)->get('/trash?type=customers')->assertOk()->assertSee('أبو أحمد');

        $this->actingAs($owner)->post("/trash/customers/{$customer->id}/restore")->assertRedirect();
        $this->assertNotSoftDeleted('customers', ['id' => $customer->id]);
    }

    public function test_other_pharmacy_cannot_see_or_restore(): void
    {
        [$orgA, $branchA] = $this->pharmacy('A');
        [, , $ownerB] = $this->pharmacy('B');

        $customer = Customer::withoutGlobalScopes()->create(['organization_id' => $orgA->id, 'branch_id' => $branchA->id, 'name' => 'عميل الصيدلية أ']);
        $customer->delete();
        Tenancy::flush();

        $this->actingAs($ownerB)->get('/trash?type=customers')->assertOk()->assertDontSee('عميل الصيدلية أ');
        $this->actingAs($ownerB)->post("/trash/customers/{$customer->id}/restore")->assertNotFound();
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }

    public function test_profile_delete_is_soft_and_owner_is_blocked(): void
    {
        [$org, $branch, $owner] = $this->pharmacy('A');
        $cashier = User::create(['organization_id' => $org->id, 'branch_id' => $branch->id, 'username' => 'cashier' . uniqid(), 'email' => uniqid('c') . '@t.local', 'password' => 'secret123', 'is_active' => true]);
        $cashier->assignRole(PermissionCatalog::CASHIER_ROLE);

        $this->actingAs($cashier)->delete('/profile', ['password' => 'secret123'])->assertRedirect('/');
        $this->assertSoftDeleted('users', ['id' => $cashier->id, 'is_active' => false]);

        $this->actingAs($owner)->delete('/profile', ['password' => 'secret123'])->assertRedirect('/profile');
        $this->assertNotSoftDeleted('users', ['id' => $owner->id]);
    }
}
