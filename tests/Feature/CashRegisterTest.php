<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\JournalEntry;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPharmacy;
use Tests\TestCase;

/**
 * الخزائن ودفتر حركاتها: كل تغيير في الرصيد له حركة، ومجموع الحركات = الرصيد دائماً.
 * php artisan test --filter=CashRegisterTest
 */
class CashRegisterTest extends TestCase
{
    use RefreshDatabase;
    use BuildsPharmacy;

    private Organization $org;
    private Branch $branch;
    private User $owner;
    private CashRegister $drawer;
    private CashRegister $safe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = $this->makeOrg();
        $this->branch = $this->makeBranch($this->org);
        $this->owner = $this->makeOwner($this->org);
        $this->drawer = $this->createRegister('درج الكاشير', 100);
        $this->safe = $this->createRegister('خزنة المدير', 0);
    }

    private function createRegister(string $name, float $opening): CashRegister
    {
        $this->actingAs($this->owner)->post('/cash-registers', [
            'branch_id'       => $this->branch->id,
            'name'            => $name . ' ' . uniqid(),
            'opening_balance' => $opening,
        ])->assertSessionHasNoErrors();

        return Tenancy::bypass(fn () => CashRegister::latest('id')->firstOrFail());
    }

    private function movements(CashRegister $register)
    {
        return Tenancy::bypass(fn () => CashMovement::where('cash_register_id', $register->id)->orderBy('id')->get());
    }

    /** مجموع الداخل − مجموع الخارج يجب أن يساوي الرصيد المحفوظ */
    private function assertLedgerMatches(CashRegister $register): void
    {
        $moves = $this->movements($register);
        $net = $moves->where('direction', 'in')->sum('amount') - $moves->where('direction', 'out')->sum('amount');

        $this->assertEquals((float) $this->reload($register)->current_balance, round($net, 2), "سجل حركات {$register->name} لا يطابق رصيدها");
    }

    public function test_new_register_records_opening_balance(): void
    {
        $this->assertEquals(100, (float) $this->drawer->current_balance);

        $moves = $this->movements($this->drawer);
        $this->assertCount(1, $moves);
        $this->assertSame('opening', $moves->first()->type);
        $this->assertTrue(Tenancy::bypass(fn () => JournalEntry::where('ref_type', 'cash_register')->where('ref_id', $this->drawer->id)->exists()), 'لا يوجد قيد للرصيد الافتتاحي');
    }

    public function test_sale_return_expense_and_shift_all_appear_in_the_ledger(): void
    {
        $product = $this->makeProduct($this->org, 10, 6);
        $this->stockIn($product, $this->branch, 20);
        $cashier = $this->makeCashier($this->org, $this->branch);

        $this->actingAs($cashier)->post('/shifts', ['cash_register_id' => $this->drawer->id])->assertSessionHasNoErrors();
        $sale = $this->sellViaPos($cashier, [[$product, 3]], 30);
        $this->actingAs($cashier)->post("/sales/{$sale->id}/return", ['quantities' => [$sale->items()->first()->id => 1]]);
        $this->actingAs($this->owner)->post('/expenses', [
            'branch_id' => $this->branch->id, 'cash_register_id' => $this->drawer->id,
            'amount' => 15, 'expense_date' => now()->toDateString(), 'description' => 'مياه',
        ])->assertSessionHasNoErrors();

        // المتوقع 100 + 30 − 10 − 15 = 105، والمعدود 103: عجز 2
        $shift = Tenancy::bypass(fn () => \App\Models\CashShift::latest('id')->first());
        $this->actingAs($cashier)->post("/shifts/{$shift->id}/close", ['actual_balance' => 103]);

        $types = $this->movements($this->drawer)->pluck('type')->all();
        $this->assertSame(['opening', 'sale', 'sale_refund', 'expense', 'shift_shortage'], $types);
        $this->assertEquals(103, (float) $this->reload($this->drawer)->current_balance);
        $this->assertLedgerMatches($this->drawer);

        // حركات الوردية مربوطة بها
        $this->assertSame(3, $this->movements($this->drawer)->where('cash_shift_id', $shift->id)->whereIn('type', ['sale', 'sale_refund', 'expense'])->count());
    }

    public function test_deposit_and_withdraw(): void
    {
        $this->actingAs($this->owner)->post("/cash-registers/{$this->drawer->id}/deposit", ['amount' => 50, 'reason' => 'فكة من المالك'])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post("/cash-registers/{$this->drawer->id}/withdraw", ['amount' => 30, 'reason' => 'إيداع في البنك'])
            ->assertSessionHasNoErrors();

        $this->assertEquals(120, (float) $this->reload($this->drawer)->current_balance);
        $this->assertSame(['opening', 'deposit', 'withdrawal'], $this->movements($this->drawer)->pluck('type')->all());
        $this->assertLedgerMatches($this->drawer);
    }

    public function test_cannot_withdraw_more_than_balance(): void
    {
        $this->actingAs($this->owner)->post("/cash-registers/{$this->drawer->id}/withdraw", ['amount' => 500, 'reason' => 'اختبار'])
            ->assertSessionHasErrors('amount');

        $this->assertEquals(100, (float) $this->reload($this->drawer)->current_balance);
    }

    public function test_reason_is_required(): void
    {
        $this->actingAs($this->owner)->post("/cash-registers/{$this->drawer->id}/deposit", ['amount' => 10])
            ->assertSessionHasErrors('reason');
    }

    public function test_transfer_between_registers(): void
    {
        $this->actingAs($this->owner)->post('/cash-registers/transfer', [
            'from_register_id' => $this->drawer->id,
            'to_register_id'   => $this->safe->id,
            'amount'           => 70,
            'note'             => 'إيراد اليوم',
        ])->assertSessionHasNoErrors();

        $this->assertEquals(30, (float) $this->reload($this->drawer)->current_balance);
        $this->assertEquals(70, (float) $this->reload($this->safe)->current_balance);
        $this->assertSame('transfer_out', $this->movements($this->drawer)->last()->type);
        $this->assertSame('transfer_in', $this->movements($this->safe)->last()->type);
        $this->assertLedgerMatches($this->drawer);
        $this->assertLedgerMatches($this->safe);
    }

    public function test_cannot_transfer_to_another_pharmacy(): void
    {
        $orgB = $this->makeOrg();
        $foreign = $this->makeRegister($this->makeBranch($orgB), 0);

        $response = $this->actingAs($this->owner)->post('/cash-registers/transfer', [
            'from_register_id' => $this->drawer->id,
            'to_register_id'   => $foreign->id,
            'amount'           => 10,
        ]);

        $this->assertTrue(in_array($response->getStatusCode(), [302, 404], true));
        $this->assertEquals(100, (float) $this->reload($this->drawer)->current_balance);
        $this->assertEquals(0, (float) $this->reload($foreign)->current_balance);
    }

    public function test_register_with_balance_cannot_be_deleted(): void
    {
        $this->actingAs($this->owner)->delete("/cash-registers/{$this->drawer->id}");
        $this->assertNotSoftDeleted('cash_registers', ['id' => $this->drawer->id]);

        $this->actingAs($this->owner)->delete("/cash-registers/{$this->safe->id}");
        $this->assertSoftDeleted('cash_registers', ['id' => $this->safe->id]);
    }

    public function test_statement_page_lists_movements(): void
    {
        $this->actingAs($this->owner)->post("/cash-registers/{$this->drawer->id}/deposit", ['amount' => 25, 'reason' => 'فكة صباحية']);

        $this->actingAs($this->owner)->get("/cash-registers/{$this->drawer->id}")
            ->assertOk()
            ->assertSee('فكة صباحية')
            ->assertSee('125.00');

        $this->actingAs($this->owner)->get('/cash-registers')->assertOk()->assertSee($this->drawer->name);
    }

    public function test_cashier_cannot_move_money_manually(): void
    {
        $cashier = $this->makeCashier($this->org, $this->branch);

        $this->assertDenied($this->actingAs($cashier)->post("/cash-registers/{$this->drawer->id}/withdraw", ['amount' => 10, 'reason' => 'x']));
        $this->assertEquals(100, (float) $this->reload($this->drawer)->current_balance);
    }

    public function test_debt_collection_enters_register_and_counts_as_collected_today(): void
    {
        $customer = $this->makeCustomer($this->org, $this->branch, 50);

        $this->actingAs($this->owner)->putJson("/customers/debts/{$customer->id}", ['amount' => 30, 'type' => 'subtract'])
            ->assertOk()->assertJson(['success' => true]);

        $this->assertEquals(20, (float) $this->reload($customer)->balance);
        $this->assertSame('debt_collection', $this->movements($this->drawer)->last()->type);
        $this->assertEquals(130, (float) $this->reload($this->drawer)->current_balance);
        $this->assertLedgerMatches($this->drawer);

        $figures = $this->actingAs($this->owner)->get('/dashboard')->assertOk()->viewData('todayFigures');
        $this->assertEquals(30, $figures['collected'], 'تحصيل الدين لا يظهر في المحصّل');
        $this->assertEquals(30, $figures['debt_collected']);
        $this->assertEquals(0, $figures['credit']);
    }
}
