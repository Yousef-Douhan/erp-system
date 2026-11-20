<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockCount;
use App\Models\User;
use App\Support\Stock;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPharmacy;
use Tests\TestCase;

/**
 * نقل المخزون بين الفروع، والجرد وترحيله.
 * php artisan test --filter=StockOperationsTest
 */
class StockOperationsTest extends TestCase
{
    use RefreshDatabase;
    use BuildsPharmacy;

    private Organization $org;
    private Branch $branch;
    private Branch $otherBranch;
    private Product $product;
    private ProductBatch $batch;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = $this->makeOrg();
        $this->branch = $this->makeBranch($this->org);
        $this->otherBranch = $this->makeBranch($this->org);
        $this->product = $this->makeProduct($this->org, 10, 6);
        $this->batch = $this->stockIn($this->product, $this->branch, 50);
        $this->owner = $this->makeOwner($this->org);
    }

    private function transfer(int $from, int $to, int $qty)
    {
        return $this->actingAs($this->owner)->post('/stock-transfers', [
            'from_warehouse_id' => $from,
            'to_warehouse_id'   => $to,
            'items'             => [['batch_id' => $this->batch->id, 'quantity' => $qty]],
        ]);
    }

    public function test_transfer_moves_stock_between_branches(): void
    {
        $this->transfer(Stock::warehouseFor($this->branch->id), Stock::warehouseFor($this->otherBranch->id), 20)
            ->assertSessionHasNoErrors();

        $this->assertSame(30, $this->branchQty($this->product, $this->branch));
        $this->assertSame(20, $this->branchQty($this->product, $this->otherBranch));
        $this->assertEquals(50, (float) $this->reload($this->product)->stock_quantity, 'الرصيد الكلي يجب ألا يتغير بالنقل');
    }

    public function test_cannot_transfer_more_than_the_batch(): void
    {
        $this->transfer(Stock::warehouseFor($this->branch->id), Stock::warehouseFor($this->otherBranch->id), 60)
            ->assertSessionHasErrors('items');

        $this->assertSame(50, $this->branchQty($this->product, $this->branch));
    }

    public function test_cannot_transfer_to_another_pharmacy(): void
    {
        $orgB = $this->makeOrg();
        $branchB = $this->makeBranch($orgB);

        $this->transfer(Stock::warehouseFor($this->branch->id), Stock::warehouseFor($branchB->id), 10)
            ->assertSessionHasErrors();

        $this->assertSame(50, $this->branchQty($this->product, $this->branch));
        $this->assertSame(0, $this->branchQty($this->product, $branchB));
    }

    public function test_stock_count_posting_adjusts_branch_stock(): void
    {
        $this->actingAs($this->owner)->post('/stock-counts', ['branch_id' => $this->branch->id])
            ->assertSessionHasNoErrors();

        $count = Tenancy::bypass(fn () => StockCount::latest('id')->firstOrFail());
        $item = Tenancy::bypass(fn () => $count->items()->where('product_id', $this->product->id)->firstOrFail());

        // المعدود فعلياً 45 بدل 50
        $this->actingAs($this->owner)->put("/stock-counts/{$count->id}", ['counted' => [$item->id => 45]])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post("/stock-counts/{$count->id}/post")
            ->assertSessionHasNoErrors();

        $this->assertSame(45, $this->branchQty($this->product, $this->branch));
        $this->assertSame('posted', $this->reload($count)->status);
    }

    public function test_posted_count_cannot_be_posted_twice(): void
    {
        $this->actingAs($this->owner)->post('/stock-counts', ['branch_id' => $this->branch->id]);
        $count = Tenancy::bypass(fn () => StockCount::latest('id')->firstOrFail());
        $item = Tenancy::bypass(fn () => $count->items()->firstOrFail());

        $this->actingAs($this->owner)->put("/stock-counts/{$count->id}", ['counted' => [$item->id => 40]]);
        $this->actingAs($this->owner)->post("/stock-counts/{$count->id}/post");
        $this->actingAs($this->owner)->post("/stock-counts/{$count->id}/post")->assertSessionHasErrors('count');

        $this->assertSame(40, $this->branchQty($this->product, $this->branch));
    }

    public function test_movements_log_is_read_only(): void
    {
        $this->actingAs($this->owner)->get('/inventory-transactions?type=opening_stock')
            ->assertOk()
            ->assertSee($this->product->name)
            ->assertSee('رصيد افتتاحي');

        // لا إضافة ولا تعديل ولا حذف يدوي
        $this->actingAs($this->owner)->post('/inventory-transactions', [
            'product_id' => $this->product->id, 'warehouse_id' => Stock::warehouseFor($this->branch->id), 'type' => 'in', 'qty_in' => 100,
        ])->assertStatus(405);

        $movementId = Tenancy::bypass(fn () => \App\Models\InventoryTransaction::value('id'));
        $this->assertContains($this->actingAs($this->owner)->delete("/inventory-transactions/{$movementId}")->getStatusCode(), [404, 405]);

        $this->assertSame(50, $this->branchQty($this->product, $this->branch));
    }
}
