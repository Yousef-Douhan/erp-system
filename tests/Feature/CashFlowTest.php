<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\CashShift;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPharmacy;
use Tests\TestCase;

/**
 * الصندوق: الورديات (فتح، بيع، إغلاق مع عجز/زيادة) والمصروفات.
 * php artisan test --filter=CashFlowTest
 */
class CashFlowTest extends TestCase
{
    use RefreshDatabase;
    use BuildsPharmacy;

    private Organization $org;
    private Branch $branch;
    private Product $product;
    private CashRegister $register;
    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = $this->makeOrg();
        $this->branch = $this->makeBranch($this->org);
        $this->product = $this->makeProduct($this->org, 10, 6);
        $this->stockIn($this->product, $this->branch, 50);
        $this->register = $this->makeRegister($this->branch, 100);
        $this->cashier = $this->makeCashier($this->org, $this->branch);
    }

    private function openShift(): CashShift
    {
        $this->actingAs($this->cashier)->post('/shifts', ['cash_register_id' => $this->register->id])
            ->assertSessionHasNoErrors();

        return Tenancy::bypass(fn () => CashShift::latest('id')->firstOrFail());
    }

    public function test_shift_close_records_shortage(): void
    {
        $shift = $this->openShift();
        $this->sellViaPos($this->cashier, [[$this->product, 2]], 20);

        // المتوقع 120، والمعدود 115: عجز 5
        $this->actingAs($this->cashier)->post("/shifts/{$shift->id}/close", ['actual_balance' => 115])
            ->assertSessionHasNoErrors();

        $shift = $this->reload($shift);
        $this->assertSame('closed', $shift->status);
        $this->assertEquals(120, (float) $shift->expected_balance);
        $this->assertEquals(-5, (float) $shift->difference);
        $this->assertEquals(115, (float) $this->reload($this->register)->current_balance);
    }

    public function test_shift_close_records_surplus(): void
    {
        $shift = $this->openShift();

        $this->actingAs($this->cashier)->post("/shifts/{$shift->id}/close", ['actual_balance' => 103]);

        $this->assertEquals(3, (float) $this->reload($shift)->difference);
    }

    public function test_cannot_open_two_shifts_on_one_register(): void
    {
        $this->openShift();
        $other = $this->makeCashier($this->org, $this->branch);

        $this->actingAs($other)->post('/shifts', ['cash_register_id' => $this->register->id])
            ->assertSessionHasErrors('cash_register_id');
    }

    public function test_expense_is_withdrawn_from_register(): void
    {
        $owner = $this->makeOwner($this->org);

        $this->actingAs($owner)->post('/expenses', [
            'branch_id'        => $this->branch->id,
            'cash_register_id' => $this->register->id,
            'amount'           => 30,
            'expense_date'     => now()->toDateString(),
            'description'      => 'كهرباء',
        ])->assertSessionHasNoErrors();

        $this->assertEquals(70, (float) $this->reload($this->register)->current_balance);
    }

    public function test_expense_cannot_exceed_register_balance(): void
    {
        $owner = $this->makeOwner($this->org);

        $this->actingAs($owner)->post('/expenses', [
            'branch_id'        => $this->branch->id,
            'cash_register_id' => $this->register->id,
            'amount'           => 500,
            'expense_date'     => now()->toDateString(),
        ])->assertSessionHasErrors('amount');

        $this->assertEquals(100, (float) $this->reload($this->register)->current_balance);
    }
}
