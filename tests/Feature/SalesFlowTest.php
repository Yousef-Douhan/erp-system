<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPharmacy;
use Tests\TestCase;

/**
 * البيع من نقطة البيع: المخزون، الخزينة، الديون، والقيود على البيع.
 * php artisan test --filter=SalesFlowTest
 */
class SalesFlowTest extends TestCase
{
    use RefreshDatabase;
    use BuildsPharmacy;

    private Organization $org;
    private Branch $branch;
    private Branch $otherBranch;
    private Product $product;
    private CashRegister $register;
    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = $this->makeOrg();
        $this->branch = $this->makeBranch($this->org);
        $this->otherBranch = $this->makeBranch($this->org);
        $this->product = $this->makeProduct($this->org, 10, 6);
        $this->stockIn($this->product, $this->branch, 50);
        $this->stockIn($this->product, $this->otherBranch, 5);
        $this->register = $this->makeRegister($this->branch, 100);
        $this->cashier = $this->makeCashier($this->org, $this->branch);
    }

    public function test_cash_sale_reduces_branch_stock_and_fills_register(): void
    {
        $sale = $this->sellViaPos($this->cashier, [[$this->product, 3]], 30);

        $this->assertEquals(30, (float) $sale->total);
        $this->assertEquals(30, (float) $sale->paid_amount);
        $this->assertSame('paid', $sale->status);
        $this->assertSame($this->branch->id, (int) $sale->branch_id);

        $this->assertSame(47, $this->branchQty($this->product, $this->branch));
        $this->assertSame(5, $this->branchQty($this->product, $this->otherBranch), 'البيع أثّر على مخزون فرع آخر');
        $this->assertEquals(130, (float) $this->reload($this->register)->current_balance);
    }

    public function test_change_is_not_kept_in_register(): void
    {
        // الزبون دفع 50 لفاتورة 30: الخزينة تزيد 30 فقط (الباقي فكة)
        $sale = $this->sellViaPos($this->cashier, [[$this->product, 3]], 50);

        $this->assertEquals(30, (float) $sale->paid_amount);
        $this->assertEquals(130, (float) $this->reload($this->register)->current_balance);
    }

    public function test_invoice_discount_is_applied(): void
    {
        $sale = $this->sellViaPos($this->cashier, [[$this->product, 3]], 25, ['discount' => 5]);

        $this->assertEquals(25, (float) $sale->total);
        $this->assertSame('paid', $sale->status);
    }

    public function test_cannot_sell_more_than_branch_stock(): void
    {
        $this->ensureCash();

        $this->actingAs($this->cashier)->post('/pos/store', [
            'items'          => [['product_id' => $this->product->id, 'quantity' => 51]],
            'payment_method' => 'cash',
            'paid_amount'    => 510,
        ])->assertSessionHasErrors('items');

        $this->assertSame(50, $this->branchQty($this->product, $this->branch));
    }

    public function test_credit_sale_needs_a_customer(): void
    {
        $this->ensureCash();

        $this->actingAs($this->cashier)->post('/pos/store', [
            'items'          => [['product_id' => $this->product->id, 'quantity' => 2]],
            'payment_method' => 'cash',
            'paid_amount'    => 5,
        ])->assertSessionHasErrors('paid_amount');

        $this->assertSame(50, $this->branchQty($this->product, $this->branch));
    }

    public function test_credit_sale_adds_to_customer_debt(): void
    {
        $customer = $this->makeCustomer($this->org, $this->branch);

        $sale = $this->sellViaPos($this->cashier, [[$this->product, 2]], 0, [
            'payment_method' => 'debt',
            'customer_id'    => $customer->id,
        ]);

        $this->assertSame('unpaid', $sale->status);
        $this->assertEquals(20, (float) $this->reload($customer)->balance);
        $this->assertEquals(100, (float) $this->reload($this->register)->current_balance, 'البيع الآجل أدخل مالاً للخزينة');
    }

    public function test_extra_payment_settles_old_debt(): void
    {
        $customer = $this->makeCustomer($this->org, $this->branch, 20);

        // فاتورة 10 ودفع 25: 10 للفاتورة و15 من الدين القديم
        $this->sellViaPos($this->cashier, [[$this->product, 1]], 25, ['customer_id' => $customer->id]);

        $this->assertEquals(5, (float) $this->reload($customer)->balance);
        $this->assertEquals(125, (float) $this->reload($this->register)->current_balance);

        // في كشف الخزينة: سطر للفاتورة (10) وسطر لتحصيل الدين (15)
        $moves = Tenancy::bypass(fn () => \App\Models\CashMovement::where('cash_register_id', $this->register->id)->orderBy('id')->get());
        $this->assertSame(['sale', 'debt_collection'], $moves->pluck('type')->all());
        $this->assertEquals([10, 15], $moves->pluck('amount')->map(fn ($v) => (float) $v)->all());

        // الرئيسية: مبيعات 10، محصّل 25 منها 15 تحصيل ديون، ولا آجل
        $figures = $this->actingAs($this->makeOwner($this->org))->get('/dashboard')->assertOk()->viewData('todayFigures');
        $this->assertEquals(10, $figures['sales']);
        $this->assertEquals(25, $figures['collected']);
        $this->assertEquals(15, $figures['debt_collected']);
        $this->assertEquals(0, $figures['credit']);
    }

    public function test_controlled_drug_needs_prescription(): void
    {
        $controlled = $this->makeProduct($this->org, 10, 6, ['is_controlled' => true]);
        $this->stockIn($controlled, $this->branch, 10);
        $this->ensureCash();

        $this->actingAs($this->cashier)->post('/pos/store', [
            'items'          => [['product_id' => $controlled->id, 'quantity' => 1]],
            'payment_method' => 'cash',
            'paid_amount'    => 10,
        ])->assertSessionHasErrors('prescription_id');

        $this->assertSame(10, $this->branchQty($controlled, $this->branch));
    }

    public function test_expired_stock_cannot_be_sold(): void
    {
        $expired = $this->makeProduct($this->org, 10, 6);
        $this->stockIn($expired, $this->branch, 10, now()->subDay()->toDateString());
        $this->ensureCash();

        $this->actingAs($this->cashier)->post('/pos/store', [
            'items'          => [['product_id' => $expired->id, 'quantity' => 1]],
            'payment_method' => 'cash',
            'paid_amount'    => 10,
        ])->assertSessionHasErrors('items');
    }

    public function test_inactive_product_cannot_be_sold(): void
    {
        $this->product->update(['is_active' => false]);
        $this->ensureCash();

        $this->actingAs($this->cashier)->post('/pos/store', [
            'items'          => [['product_id' => $this->product->id, 'quantity' => 1]],
            'payment_method' => 'cash',
            'paid_amount'    => 10,
        ])->assertSessionHasErrors();

        $this->assertSame(50, $this->branchQty($this->product, $this->branch));
    }

    public function test_cashier_cannot_sell_another_pharmacys_product(): void
    {
        $orgB = $this->makeOrg();
        $branchB = $this->makeBranch($orgB);
        $cashierB = $this->makeCashier($orgB, $branchB);
        $this->makeRegister($branchB);
        $this->ensureCash();

        $this->actingAs($cashierB)->post('/pos/store', [
            'items'          => [['product_id' => $this->product->id, 'quantity' => 1]],
            'payment_method' => 'cash',
            'paid_amount'    => 10,
        ])->assertSessionHasErrors();

        $this->assertSame(50, $this->branchQty($this->product, $this->branch));
        $this->assertSame(0, Tenancy::bypass(fn () => Sale::count()));
    }

    public function test_deleting_a_sale_restores_stock_and_keeps_the_record(): void
    {
        $sale = $this->sellViaPos($this->cashier, [[$this->product, 4]], 40);
        $this->assertSame(46, $this->branchQty($this->product, $this->branch));

        $owner = $this->makeOwner($this->org);
        $this->actingAs($owner)->delete("/sales/{$sale->id}")->assertSessionHasNoErrors();

        $this->assertSame(50, $this->branchQty($this->product, $this->branch));
        $this->assertEquals(100, (float) $this->reload($this->register)->current_balance, 'مبلغ الفاتورة المحذوفة لم يُسحب من الخزينة');
        $this->assertSoftDeleted('sales', ['id' => $sale->id]);
    }

    public function test_cashier_cannot_delete_sales(): void
    {
        $sale = $this->sellViaPos($this->cashier, [[$this->product, 1]], 10);

        $this->assertDenied($this->actingAs($this->cashier)->delete("/sales/{$sale->id}"));
        $this->assertNotSoftDeleted('sales', ['id' => $sale->id]);
    }

    public function test_wallet_overpayment_counts_as_collected_debt(): void
    {
        // نفس حالة «بنك فلسطين»: فاتورة 10، دفع 25 بالمحفظة/البنك، وعليه دين 20
        \App\Models\PaymentMethod::updateOrCreate(['code' => 'bank_test'], ['name' => 'بنك', 'is_active' => true, 'requires_wallet' => true]);
        $customer = $this->makeCustomer($this->org, $this->branch, 20);

        $this->sellViaPos($this->cashier, [[$this->product, 1]], 25, [
            'customer_id' => $customer->id, 'payment_method' => 'bank_test', 'wallet_phone' => '0599000000',
        ]);

        $this->assertEquals(5, (float) $this->reload($customer)->balance);
        $this->assertEquals(100, (float) $this->reload($this->register)->current_balance, 'الدفع غير النقدي لا يدخل الخزينة');

        $figures = $this->actingAs($this->makeOwner($this->org))->get('/dashboard')->assertOk()->viewData('todayFigures');
        $this->assertEquals(10, $figures['sales']);
        $this->assertEquals(25, $figures['collected']);
        $this->assertEquals(15, $figures['debt_collected']);
    }
}
