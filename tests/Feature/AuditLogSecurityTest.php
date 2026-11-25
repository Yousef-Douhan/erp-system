<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * سجل التدقيق معزول بين الصيدليات وغير قابل للحذف أو التعديل،
 * والفرع الافتراضي لا يرجع أبداً لفرع صيدلية أخرى.
 */
class AuditLogSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;

    private Organization $orgB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orgA = Organization::create(['name' => 'صيدلية أ', 'currency' => 'ILS', 'status' => 'active']);
        $this->orgB = Organization::create(['name' => 'صيدلية ب', 'currency' => 'ILS', 'status' => 'active']);
        Branch::create(['organization_id' => $this->orgA->id, 'name' => 'فرع أ', 'status' => 'active']);
        Branch::create(['organization_id' => $this->orgB->id, 'name' => 'فرع ب', 'status' => 'active']);
    }

    private function ownerOf(Organization $org, bool $canViewAuditLogs = true): User
    {
        $user = User::create([
            'organization_id' => $org->id,
            'username' => 'u'.uniqid(),
            'email' => uniqid().'@test.local',
            'password' => 'secret123',
            'is_active' => true,
        ]);
        $user->assignRole(PermissionCatalog::OWNER_ROLE);
        if ($canViewAuditLogs) {
            $user->givePermissionTo('view_audit_logs');
        }
        Tenancy::flush();

        return $user;
    }

    private function productFor(Organization $org, string $name): Product
    {
        return Tenancy::bypass(fn () => Product::create([
            'organization_id' => $org->id,
            'name' => $name,
            'purchase_price' => 1,
            'selling_price' => 2,
            'stock_quantity' => 0,
            'alert_quantity' => 1,
        ]));
    }

    public function test_audit_logs_are_tagged_with_the_affected_pharmacy(): void
    {
        $product = $this->productFor($this->orgB, 'دواء ب');

        $log = AuditLog::withoutGlobalScopes()->where('table_name', 'products')->where('record_id', $product->id)->first();

        $this->assertSame($this->orgB->id, (int) $log->organization_id);
    }

    public function test_pharmacy_cannot_see_audit_logs_of_another_pharmacy(): void
    {
        $this->productFor($this->orgA, 'دواء أ');
        $productB = $this->productFor($this->orgB, 'دواء سري لصيدلية ب');
        $logB = AuditLog::withoutGlobalScopes()->where('table_name', 'products')->where('record_id', $productB->id)->first();

        $this->actingAs($this->ownerOf($this->orgA));

        $this->assertFalse(AuditLog::where('organization_id', $this->orgB->id)->exists());
        $this->get('/audit-logs')->assertOk()->assertDontSee('دواء سري لصيدلية ب');
        $this->get('/audit-logs/'.$logB->id)->assertNotFound();
    }

    public function test_audit_logs_cannot_be_deleted_or_edited(): void
    {
        $product = $this->productFor($this->orgA, 'دواء أ');
        $log = AuditLog::withoutGlobalScopes()->where('record_id', $product->id)->where('table_name', 'products')->first();

        $admin = User::create(['username' => 'admin'.uniqid(), 'email' => uniqid().'@test.local', 'password' => 'secret123', 'is_active' => true]);
        $admin->assignRole(Role::findOrCreate(PermissionCatalog::ADMIN_ROLE, 'web'));
        $admin->givePermissionTo('view_audit_logs');

        $this->actingAs($admin)->delete('/audit-logs/'.$log->id)->assertStatus(405);
        $this->assertNotSoftDeleted($log);

        $this->expectException(LogicException::class);
        $log->delete();
    }

    public function test_audit_logs_cannot_be_updated(): void
    {
        $product = $this->productFor($this->orgA, 'دواء أ');
        $log = AuditLog::withoutGlobalScopes()->where('record_id', $product->id)->where('table_name', 'products')->first();

        $this->expectException(LogicException::class);
        $log->update(['action' => 'tampered']);
    }

    public function test_audit_log_page_escapes_recorded_values(): void
    {
        $this->productFor($this->orgA, '<img src=x onerror=alert(1)>${alert(2)}');

        $this->actingAs($this->ownerOf($this->orgA))
            ->get('/audit-logs')
            ->assertOk()
            ->assertDontSee('<img src=x', false)
            ->assertDontSee('`${alert', false);
    }

    public function test_default_branch_never_falls_back_to_another_pharmacy(): void
    {
        $orgWithoutBranches = Organization::create(['name' => 'صيدلية بلا فروع', 'currency' => 'ILS', 'status' => 'active']);
        $this->actingAs($this->ownerOf($orgWithoutBranches, false));

        $this->expectException(ValidationException::class);
        Tenancy::defaultBranchId();
    }

    public function test_default_branch_is_the_users_own_pharmacy_branch(): void
    {
        $this->actingAs($this->ownerOf($this->orgB, false));

        $branchB = Branch::withoutGlobalScopes()->where('organization_id', $this->orgB->id)->value('id');

        $this->assertSame((int) $branchB, Tenancy::defaultBranchId());
    }
}
