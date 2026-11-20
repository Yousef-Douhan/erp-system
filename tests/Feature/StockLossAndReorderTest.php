<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\InventoryTransaction;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockDisposal;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPharmacy;
use Tests\TestCase;

/**
 * الإتلاف، صفحة الدفعات (للعرض فقط)، اقتراح الطلبية، وتقرير المبيعات.
 * php artisan test --filter=StockLossAndReorderTest
 */
class StockLossAndReorderTest extends TestCase
{
    use RefreshDatabase;
    use BuildsPharmacy;

    private Organization $org;
    private Branch $branch;
    private User $owner;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = $this->makeOrg();
        $this->branch = $this->makeBranch($this->org);
        $this->owner = $this->makeOwner($this->org);
        $this->product = $this->makeProduct($this->org, 10, 4);
        $this->makeRegister($this->branch, 100);
    }

    public function test_disposing_an_expired_batch_removes_stock_and_counts_as_loss(): void
    {
        $expired = $this->stockIn($this->product, $this->branch, 12, now()->subDays(3)->toDateString());

        $this->actingAs($this->owner)->get('/batches?status=expired')->assertOk()->assertSee($expired->batch_number);

        $this->actingAs($this->owner)->post("/batches/{$expired->id}/dispose", ['quantity' => 12, 'reason' => 'expired'])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $this->branchQty($this->product, $this->branch));
        $this->assertSame(0, (int) $this->reload($expired)->quantity);
        $this->assertTrue(Tenancy::bypass(fn () => InventoryTransaction::where('type', 'disposal')->where('qty_out', 12)->exists()));

        $disposal = Tenancy::bypass(fn () => StockDisposal::firstOrFail());
        $this->assertEquals(48, (float) $disposal->total_cost);

        // الخسارة تظهر في تقرير الأرباح وصافي الربح ينقص بها
        $summary = $this->actingAs($this->owner)->get('/reports/profit')->assertOk()->viewData('summary');
        $this->assertEquals(48, $summary['disposals']);
        $this->assertEquals(-48, $summary['net']);

        $this->actingAs($this->owner)->get('/disposals')->assertOk()->assertSee($this->product->name);
    }

    public function test_cannot_dispose_more_than_the_batch(): void
    {
        $batch = $this->stockIn($this->product, $this->branch, 5);

        $this->actingAs($this->owner)->post("/batches/{$batch->id}/dispose", ['quantity' => 6, 'reason' => 'damaged'])
            ->assertSessionHasErrors('quantity');
        $this->assertSame(5, $this->branchQty($this->product, $this->branch));
    }

    public function test_cashier_cannot_dispose(): void
    {
        $batch = $this->stockIn($this->product, $this->branch, 5);
        $cashier = $this->makeCashier($this->org, $this->branch);

        $this->assertDenied($this->actingAs($cashier)->post("/batches/{$batch->id}/dispose", ['quantity' => 1, 'reason' => 'damaged']));
        $this->assertSame(5, $this->branchQty($this->product, $this->branch));
    }

    public function test_batches_page_cannot_change_quantities(): void
    {
        $batch = $this->stockIn($this->product, $this->branch, 10);

        // لا إضافة دفعة من العدم ولا حذف
        $this->assertContains($this->actingAs($this->owner)->post('/batches', [
            'product_id' => $this->product->id, 'warehouse_id' => $batch->warehouse_id, 'batch_number' => 'X', 'cost_price' => 1, 'quantity' => 999,
        ])->getStatusCode(), [404, 405]);
        $this->assertContains($this->actingAs($this->owner)->delete("/batches/{$batch->id}")->getStatusCode(), [404, 405]);

        // التعديل يغيّر التاريخ فقط حتى لو أُرسلت كمية
        $newExpiry = now()->addMonths(18)->toDateString();
        $this->actingAs($this->owner)->put("/batches/{$batch->id}", ['expiry_date' => $newExpiry, 'quantity' => 500])
            ->assertSessionHasNoErrors();

        $batch = $this->reload($batch);
        $this->assertSame(10, (int) $batch->quantity);
        $this->assertSame($newExpiry, \Illuminate\Support\Carbon::parse($batch->expiry_date)->toDateString());
        $this->assertSame(10, $this->branchQty($this->product, $this->branch));
        $this->assertSame(1, Tenancy::bypass(fn () => ProductBatch::where('product_id', $this->product->id)->count()));
    }

    public function test_reorder_suggests_low_and_fast_moving_items(): void
    {
        $low = $this->makeProduct($this->org, 10, 4, ['alert_quantity' => 10]);
        $this->stockIn($low, $this->branch, 3);
        $plenty = $this->makeProduct($this->org, 10, 4, ['alert_quantity' => 2]);
        $this->stockIn($plenty, $this->branch, 500);

        $lines = $this->actingAs($this->owner)->get("/reorder?branch_id={$this->branch->id}")->assertOk()->viewData('lines');
        $names = $lines->pluck('name');

        $this->assertTrue($names->contains($low->name), 'الصنف تحت الحد الأدنى لم يظهر في الطلبية');
        $this->assertFalse($names->contains($plenty->name), 'صنف كثير المخزون ظهر في الطلبية');
        // الهدف = ضعف الحد الأدنى (20) − الرصيد (3)
        $this->assertSame(17, $lines->firstWhere('name', $low->name)['suggest']);

        // الصفحة لا تغيّر المخزون
        $this->assertSame(3, $this->branchQty($low, $this->branch));
    }

    public function test_sales_report_and_best_sellers(): void
    {
        $this->stockIn($this->product, $this->branch, 50);
        $other = $this->makeProduct($this->org, 5, 2);
        $this->stockIn($other, $this->branch, 50);
        $cashier = $this->makeCashier($this->org, $this->branch);

        $this->sellViaPos($cashier, [[$this->product, 3]], 30);
        $this->sellViaPos($cashier, [[$other, 1]], 5);

        $response = $this->actingAs($this->owner)->get('/reports/sales')->assertOk();
        $summary = $response->viewData('summary');
        $top = $response->viewData('top');

        $this->assertSame(2, $summary['count']);
        $this->assertEquals(35, $summary['net']);
        $this->assertEquals(35, $summary['cash']);
        $this->assertSame($this->product->name, $top->first()['name']);

        $this->actingAs($this->owner)->get('/reports/sales?group=month&export=csv')->assertOk();
        $this->assertDenied($this->actingAs($cashier)->get('/reports/sales'));
    }
}
