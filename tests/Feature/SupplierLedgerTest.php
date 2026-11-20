<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\JournalEntry;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\SupplierAccount;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPharmacy;
use Tests\TestCase;

/**
 * ما علينا للمورد: فواتير الشراء تزيده، والدفعات تنقصه، والكشف والقيود تتبعهما.
 * php artisan test --filter=SupplierLedgerTest
 */
class SupplierLedgerTest extends TestCase
{
    use RefreshDatabase;
    use BuildsPharmacy;

    private Organization $org;
    private Branch $branch;
    private User $owner;
    private Supplier $supplier;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = $this->makeOrg();
        $this->branch = $this->makeBranch($this->org);
        $this->owner = $this->makeOwner($this->org);
        $this->supplier = $this->makeSupplier($this->org);
        $this->product = $this->makeProduct($this->org, 10, 6);
        $this->makeRegister($this->branch, 1000);
    }

    private function purchase(array $extra = []): Purchase
    {
        $this->actingAs($this->owner)->post('/purchases', array_merge([
            'branch_id'      => $this->branch->id,
            'supplier_id'    => $this->supplier->id,
            'invoice_number' => 'P-' . uniqid(),
        ], $extra))->assertSessionHasNoErrors();

        return Tenancy::bypass(fn () => Purchase::latest('id')->firstOrFail());
    }

    private function addItem(Purchase $purchase, int $qty, float $cost): void
    {
        $this->actingAs($this->owner)->post("/purchases/{$purchase->id}/items", [
            'product_id' => $this->product->id, 'quantity' => $qty, 'unit_cost' => $cost,
        ])->assertSessionHasNoErrors();
    }

    public function test_invoice_items_raise_what_we_owe_the_supplier(): void
    {
        $purchase = $this->purchase();
        $this->addItem($purchase, 20, 5);

        $this->assertEquals(100, (float) $this->reload($this->supplier)->balance);

        $rows = Tenancy::bypass(fn () => SupplierAccount::where('supplier_id', $this->supplier->id)->get());
        $this->assertSame(['purchase_invoice'], $rows->pluck('transaction_type')->all());
        $this->assertEquals(100, (float) $rows->first()->credit);

        $this->assertTrue(Tenancy::bypass(fn () => JournalEntry::where('ref_type', 'purchase')->where('ref_id', $purchase->id)->exists()), 'لا يوجد قيد لفاتورة الشراء');
    }

    public function test_payment_lowers_balance_and_statement_shows_both(): void
    {
        $purchase = $this->purchase();
        $this->addItem($purchase, 20, 5);

        $register = Tenancy::bypass(fn () => \App\Models\CashRegister::latest('id')->first());
        $this->actingAs($this->owner)->post("/purchases/{$purchase->id}/pay", [
            'amount' => 40, 'payment_method' => 'cash', 'cash_register_id' => $register->id,
        ])->assertSessionHasNoErrors();

        $this->assertEquals(60, (float) $this->reload($this->supplier)->balance);

        $this->actingAs($this->owner)->get("/suppliers/{$this->supplier->id}/statement")
            ->assertOk()
            ->assertSee('فاتورة شراء')
            ->assertSee('دفعة للمورد')
            ->assertSee('60.00');
    }

    public function test_paid_amount_cannot_be_typed_on_the_invoice(): void
    {
        $purchase = $this->purchase(['total' => 200, 'paid_amount' => 200]);

        $this->assertEquals(0, (float) $purchase->paid_amount);
        $this->assertSame('unpaid', $purchase->status);
        $this->assertEquals(200, (float) $this->reload($this->supplier)->balance);
    }

    public function test_deleting_an_invoice_reverses_the_debt(): void
    {
        $purchase = $this->purchase();
        $this->addItem($purchase, 10, 5);
        $this->assertEquals(50, (float) $this->reload($this->supplier)->balance);

        $this->actingAs($this->owner)->delete("/purchases/{$purchase->id}")->assertSessionHasNoErrors();

        $this->assertEquals(0, (float) $this->reload($this->supplier)->balance);
    }

    public function test_changing_supplier_moves_the_debt(): void
    {
        $other = $this->makeSupplier($this->org);
        $purchase = $this->purchase(['total' => 200]);

        $this->actingAs($this->owner)->put("/purchases/{$purchase->id}", [
            'branch_id' => $this->branch->id, 'supplier_id' => $other->id,
            'invoice_number' => $purchase->invoice_number, 'total' => 200,
        ])->assertSessionHasNoErrors();

        $this->assertEquals(0, (float) $this->reload($this->supplier)->balance);
        $this->assertEquals(200, (float) $this->reload($other)->balance);
    }

    public function test_balance_cannot_be_edited_by_hand(): void
    {
        $this->purchase(['total' => 150]);

        $this->actingAs($this->owner)->put("/suppliers/{$this->supplier->id}", [
            'organization_id' => $this->org->id, 'name' => $this->supplier->name,
            'credit_limit' => 0, 'balance' => 999,
        ])->assertSessionHasNoErrors();

        $this->assertEquals(150, (float) $this->reload($this->supplier)->balance);
    }

    public function test_opening_balance_appears_in_statement(): void
    {
        $this->actingAs($this->owner)->post('/suppliers', [
            'organization_id' => $this->org->id, 'name' => 'مورد قديم ' . uniqid(),
            'balance' => 300, 'credit_limit' => 0,
        ])->assertSessionHasNoErrors();

        $supplier = Tenancy::bypass(fn () => Supplier::latest('id')->first());
        $this->assertEquals(300, (float) $supplier->balance);
        $this->assertSame('opening', Tenancy::bypass(fn () => SupplierAccount::where('supplier_id', $supplier->id)->value('transaction_type')));
    }
}
