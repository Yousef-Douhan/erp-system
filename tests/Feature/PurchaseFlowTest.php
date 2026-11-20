<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPharmacy;
use Tests\TestCase;

/**
 * المشتريات: التوريد يزيد مخزون الفرع، حماية سعر الشراء، ودفعات الموردين من الخزينة.
 * php artisan test --filter=PurchaseFlowTest
 */
class PurchaseFlowTest extends TestCase
{
    use RefreshDatabase;
    use BuildsPharmacy;

    private Organization $org;
    private Branch $branch;
    private Product $product;
    private Supplier $supplier;
    private CashRegister $register;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = $this->makeOrg();
        $this->branch = $this->makeBranch($this->org);
        $this->product = $this->makeProduct($this->org, 10, 6);
        $this->supplier = $this->makeSupplier($this->org);
        $this->register = $this->makeRegister($this->branch, 200);
        $this->owner = $this->makeOwner($this->org);
    }

    private function newPurchase(): Purchase
    {
        $this->actingAs($this->owner)->post('/purchases', [
            'branch_id'      => $this->branch->id,
            'supplier_id'    => $this->supplier->id,
            'invoice_number' => 'P-' . uniqid(),
            'total'          => 0,
            'paid_amount'    => 0,
        ])->assertSessionHasNoErrors();

        return Tenancy::bypass(fn () => Purchase::latest('id')->firstOrFail());
    }

    public function test_purchase_item_adds_stock_to_the_branch(): void
    {
        $purchase = $this->newPurchase();

        $this->actingAs($this->owner)->post("/purchases/{$purchase->id}/items", [
            'product_id'   => $this->product->id,
            'batch_number' => 'LOT-1',
            'expiry_date'  => now()->addYear()->toDateString(),
            'quantity'     => 20,
            'unit_cost'    => 5,
        ])->assertSessionHasNoErrors();

        $this->assertSame(20, $this->branchQty($this->product, $this->branch));
        $this->assertEquals(5, (float) $this->reload($this->product)->purchase_price);
        $this->assertEquals(100, (float) $this->reload($purchase)->total);
    }

    public function test_cost_above_selling_price_is_rejected(): void
    {
        $purchase = $this->newPurchase();

        // سعر العلبة بدل سعر الحبة: 12 أعلى من سعر البيع 10
        $this->actingAs($this->owner)->post("/purchases/{$purchase->id}/items", [
            'product_id' => $this->product->id,
            'quantity'   => 20,
            'unit_cost'  => 12,
        ])->assertSessionHasErrors('unit_cost');

        $this->assertSame(0, $this->branchQty($this->product, $this->branch));
    }

    public function test_supplier_payment_comes_out_of_the_register(): void
    {
        $purchase = $this->newPurchase();
        $this->actingAs($this->owner)->post("/purchases/{$purchase->id}/items", [
            'product_id' => $this->product->id,
            'quantity'   => 20,
            'unit_cost'  => 5,
        ]);

        $this->actingAs($this->owner)->post("/purchases/{$purchase->id}/pay", [
            'amount'           => 40,
            'payment_method'   => 'cash',
            'cash_register_id' => $this->register->id,
        ])->assertSessionHasNoErrors();

        $purchase = $this->reload($purchase);
        $this->assertEquals(40, (float) $purchase->paid_amount);
        $this->assertSame('partial', $purchase->status);
        $this->assertEquals(160, (float) $this->reload($this->register)->current_balance);
    }

    public function test_cannot_pay_more_than_remaining(): void
    {
        $purchase = $this->newPurchase();
        $this->actingAs($this->owner)->post("/purchases/{$purchase->id}/items", [
            'product_id' => $this->product->id,
            'quantity'   => 10,
            'unit_cost'  => 5,
        ]);

        $this->actingAs($this->owner)->post("/purchases/{$purchase->id}/pay", [
            'amount'           => 60,
            'payment_method'   => 'cash',
            'cash_register_id' => $this->register->id,
        ])->assertSessionHasErrors('amount');

        $this->assertEquals(200, (float) $this->reload($this->register)->current_balance);
    }

    public function test_cashier_cannot_open_purchases(): void
    {
        $cashier = $this->makeCashier($this->org, $this->branch);

        $this->assertDenied($this->actingAs($cashier)->get('/purchases'));
        $this->assertDenied($this->actingAs($cashier)->post('/purchases', [
            'branch_id'      => $this->branch->id,
            'supplier_id'    => $this->supplier->id,
            'invoice_number' => 'X-1',
            'total'          => 0,
        ]));
    }
}
