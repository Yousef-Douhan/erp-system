<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPharmacy;
use Tests\TestCase;

/**
 * مرتجعات المبيعات: إرجاع المخزون، رد النقد أو تخفيض الدين، وأرقام الصفحة الرئيسية.
 * php artisan test --filter=ReturnFlowTest
 */
class ReturnFlowTest extends TestCase
{
    use RefreshDatabase;
    use BuildsPharmacy;

    private Organization $org;
    private Branch $branch;
    private Product $product;
    private CashRegister $register;
    private User $cashier;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = $this->makeOrg();
        $this->branch = $this->makeBranch($this->org);
        $this->product = $this->makeProduct($this->org, 10, 6);
        $this->stockIn($this->product, $this->branch, 50);
        $this->register = $this->makeRegister($this->branch, 100);
        $this->cashier = $this->makeCashier($this->org, $this->branch);
        $this->owner = $this->makeOwner($this->org);
    }

    public function test_cash_return_restocks_and_refunds(): void
    {
        $sale = $this->sellViaPos($this->cashier, [[$this->product, 3]], 30);
        $itemId = $sale->items()->first()->id;

        $this->actingAs($this->cashier)->post("/sales/{$sale->id}/return", [
            'quantities' => [$itemId => 1],
            'restock'    => 1,
        ])->assertSessionHasNoErrors();

        $sale = $this->reload($sale);
        $this->assertEquals(10, (float) $sale->returned_amount);
        $this->assertEquals(20, (float) $sale->paid_amount);
        $this->assertSame('paid', $sale->status);
        $this->assertSame(48, $this->branchQty($this->product, $this->branch));
        $this->assertEquals(120, (float) $this->reload($this->register)->current_balance);
    }

    public function test_return_without_restock_keeps_stock(): void
    {
        $sale = $this->sellViaPos($this->cashier, [[$this->product, 3]], 30);

        $this->actingAs($this->cashier)->post("/sales/{$sale->id}/return", [
            'quantities' => [$sale->items()->first()->id => 1],
            'restock'    => 0,
        ])->assertSessionHasNoErrors();

        $this->assertSame(47, $this->branchQty($this->product, $this->branch));
    }

    public function test_cannot_return_more_than_sold(): void
    {
        $sale = $this->sellViaPos($this->cashier, [[$this->product, 3]], 30);
        $itemId = $sale->items()->first()->id;

        $this->actingAs($this->cashier)->post("/sales/{$sale->id}/return", ['quantities' => [$itemId => 2]]);
        $this->actingAs($this->cashier)->post("/sales/{$sale->id}/return", ['quantities' => [$itemId => 2]])
            ->assertSessionHasErrors('items');

        $this->assertEquals(20, (float) $this->reload($sale)->returned_amount);
    }

    public function test_credit_sale_return_reduces_debt_not_cash(): void
    {
        $customer = $this->makeCustomer($this->org, $this->branch);
        $sale = $this->sellViaPos($this->cashier, [[$this->product, 2]], 0, [
            'payment_method' => 'debt',
            'customer_id'    => $customer->id,
        ]);

        $this->actingAs($this->cashier)->post("/sales/{$sale->id}/return", [
            'quantities' => [$sale->items()->first()->id => 1],
        ])->assertSessionHasNoErrors();

        $this->assertEquals(10, (float) $this->reload($customer)->balance);
        $this->assertEquals(100, (float) $this->reload($this->register)->current_balance);
    }

    public function test_dashboard_shows_net_sales_after_returns(): void
    {
        // نفس الحالة التي ظهرت فيها "آجل" بالغلط: فاتورة 30 مدفوعة ومرتجع 10
        $sale = $this->sellViaPos($this->cashier, [[$this->product, 3]], 30);
        $this->actingAs($this->cashier)->post("/sales/{$sale->id}/return", [
            'quantities' => [$sale->items()->first()->id => 1],
        ]);

        $figures = $this->actingAs($this->owner)->get('/dashboard')->assertOk()->viewData('todayFigures');

        $this->assertEquals(20, $figures['sales']);
        $this->assertEquals(20, $figures['collected']);
        $this->assertEquals(0, $figures['sales'] - $figures['collected'], 'الآجل يجب أن يكون صفراً');
    }

    public function test_sale_with_returns_cannot_be_deleted(): void
    {
        $sale = $this->sellViaPos($this->cashier, [[$this->product, 3]], 30);
        $this->actingAs($this->cashier)->post("/sales/{$sale->id}/return", [
            'quantities' => [$sale->items()->first()->id => 1],
        ]);

        $this->actingAs($this->owner)->delete("/sales/{$sale->id}");

        $this->assertNotSoftDeleted('sales', ['id' => $sale->id]);
    }
}
