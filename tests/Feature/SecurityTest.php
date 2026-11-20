<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPharmacy;
use Tests\TestCase;

/**
 * الحماية: الدخول، الحسابات الموقوفة، فصل الفروع والصيدليات، والصلاحيات.
 * php artisan test --filter=SecurityTest
 */
class SecurityTest extends TestCase
{
    use RefreshDatabase;
    use BuildsPharmacy;

    private Organization $org;
    private Branch $branch;
    private Product $product;
    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = $this->makeOrg();
        $this->branch = $this->makeBranch($this->org);
        $this->product = $this->makeProduct($this->org);
        $this->stockIn($this->product, $this->branch, 20);
        $this->makeRegister($this->branch);
        $this->cashier = $this->makeCashier($this->org, $this->branch);
    }

    // ---------------- تسجيل الدخول ----------------

    public function test_user_can_log_in(): void
    {
        $this->post('/login', ['email' => $this->cashier->email, 'password' => 'secret123'])
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($this->cashier);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->post('/login', ['email' => $this->cashier->email, 'password' => 'wrong-pass'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_is_locked_after_five_failed_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $this->cashier->email, 'password' => 'wrong-pass']);
        }

        // حتى كلمة السر الصحيحة تُرفض أثناء القفل
        $this->post('/login', ['email' => $this->cashier->email, 'password' => 'secret123'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $this->cashier->forceFill(['is_active' => false])->save();

        $this->post('/login', ['email' => $this->cashier->email, 'password' => 'secret123'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_deactivated_user_is_kicked_out(): void
    {
        $this->cashier->forceFill(['is_active' => false])->save();

        $this->actingAs($this->cashier)->get('/dashboard')->assertRedirect('/login');
    }

    public function test_inactive_branch_blocks_its_staff(): void
    {
        $this->branch->update(['status' => 'inactive']);

        $this->actingAs($this->cashier)->get('/pos')->assertRedirect('/login');
    }

    public function test_inactive_pharmacy_blocks_its_owner(): void
    {
        $owner = $this->makeOwner($this->org);
        $this->org->update(['status' => 'inactive']);

        $this->actingAs($owner)->get('/dashboard')->assertRedirect('/login');
    }

    public function test_self_registration_is_closed(): void
    {
        if (config('auth.allow_registration')) {
            $this->markTestSkipped('ALLOW_REGISTRATION=true في .env: أغلقه قبل الرفع على السيرفر.');
        }

        $this->get('/register')->assertNotFound();
    }

    // ---------------- فصل البيانات ----------------

    public function test_cashier_cannot_open_sale_of_another_branch(): void
    {
        $otherBranch = $this->makeBranch($this->org);
        $this->stockIn($this->product, $otherBranch, 10);
        $this->makeRegister($otherBranch);
        $sale = $this->sellViaPos($this->makeCashier($this->org, $otherBranch), [[$this->product, 1]], 10);

        $this->assertDenied($this->actingAs($this->cashier)->get("/sales/{$sale->id}"));
    }

    public function test_other_pharmacy_cannot_open_sale_by_url(): void
    {
        $sale = $this->sellViaPos($this->cashier, [[$this->product, 1]], 10);
        $ownerB = $this->makeOwner($this->makeOrg());

        foreach (["/sales/{$sale->id}", "/sales/{$sale->id}/receipt", "/sales/{$sale->id}/return"] as $url) {
            $this->flushSession();
            $this->assertDenied($this->actingAs($ownerB)->get($url), $url);
        }
    }

    public function test_other_pharmacy_cannot_edit_product(): void
    {
        $ownerB = $this->makeOwner($this->makeOrg());

        $response = $this->actingAs($ownerB)->put("/products/{$this->product->id}", [
            'name'           => 'تم الاختراق',
            'purchase_price' => 1,
            'selling_price'  => 2,
        ]);

        $this->assertDenied($response);
        $this->assertNotSame('تم الاختراق', $this->reload($this->product)->name);
    }

    // ---------------- الصلاحيات ----------------

    public function test_cashier_cannot_manage_users_or_roles(): void
    {
        $this->assertDenied($this->actingAs($this->cashier)->get('/users'));
        $this->flushSession();
        $this->assertDenied($this->actingAs($this->cashier)->get('/roles'));
        $this->flushSession();
        $this->assertDenied($this->actingAs($this->cashier)->post('/users', [
            'username' => 'hacker',
            'email'    => 'hacker@test.local',
            'password' => 'secret123',
        ]));
        $this->assertFalse(User::where('username', 'hacker')->exists());
    }

    public function test_cashier_cannot_change_his_own_scope_from_profile(): void
    {
        $otherOrg = $this->makeOrg();

        $this->actingAs($this->cashier)->patch('/profile', [
            'username'        => $this->cashier->username,
            'email'           => $this->cashier->email,
            'organization_id' => $otherOrg->id,
            'branch_id'       => null,
            'is_active'       => 1,
        ]);

        $fresh = $this->reload($this->cashier);
        $this->assertSame($this->org->id, (int) $fresh->organization_id);
        $this->assertSame($this->branch->id, (int) $fresh->branch_id);
        $this->assertFalse($fresh->hasRole('Admin'));
    }

    public function test_only_admin_reaches_backups(): void
    {
        $owner = $this->makeOwner($this->org);

        $this->assertDenied($this->actingAs($owner)->get('/backups'));
        $this->actingAs($this->makeAdmin())->get('/backups')->assertOk();
    }

    public function test_backup_download_blocks_path_traversal(): void
    {
        $status = $this->actingAs($this->makeAdmin())->get('/backups/..%2F..%2F.env')->getStatusCode();

        $this->assertContains($status, [403, 404]);
    }
}
