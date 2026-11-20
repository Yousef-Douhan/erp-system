<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\InventoryTransaction;
use App\Models\JournalEntry;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\SupplierAccount;
use App\Models\User;
use App\Support\Stock;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPharmacy;
use Tests\TestCase;

/**
 * مرتجع المشتريات للمورد: المخزون، المستحق للمورد، الخزينة، والقيد.
 * php artisan test --filter=PurchaseReturnTest
 */
class PurchaseReturnTest extends TestCase
{
    use RefreshDatabase;
    use BuildsPharmacy;

    private Organization $org;
    private Branch $branch;
    private User $owner;
    private Supplier $supplier;
    private Product $product;
    private CashRegister $register;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = $this->makeOrg();
        $this->branch = $this->makeBranch($this->org);
        $this->owner = $this->makeOwner($this->org);
        $this->supplier = $this->makeSupplier($this->org);
        $this->product = $this->makeProduct($this->org, 10, 5);
        $this->register = $this->makeRegister($this->branch, 1000);
    }

    /** فاتورة شراء فيها 20 حبة × 5 = 100 بدفعة رقمها B-1 */
    private function invoice(): array
    {
        $this->actingAs($this->owner)->post('/purchases', [
            'branch_id' => $this->branch->id, 'supplier_id' => $this->supplier->id, 'invoice_number' => 'P-' . uniqid(),
        ])->assertSessionHasNoErrors();
        $purchase = Tenancy::bypass(fn () => Purchase::latest('id')->firstOrFail());

        $this->actingAs($this->owner)->post("/purchases/{$purchase->id}/items", [
            'product_id' => $this->product->id, 'batch_number' => 'B-1', 'expiry_date' => now()->addMonths(2)->toDateString(),
            'quantity' => 20, 'unit_cost' => 5,
        ])->assertSessionHasNoErrors();

        $item = Tenancy::bypass(fn () => PurchaseItem::where('purchase_id', $purchase->id)->firstOrFail());

        return [$this->reload($purchase), $item];
    }

    private function returnItems(Purchase $purchase, PurchaseItem $item, int $qty, array $extra = [])
    {
        return $this->actingAs($this->owner)->post("/purchases/{$purchase->id}/return", array_merge([
            'quantities' => [$item->id => $qty],
            'reason'     => 'قريب الانتهاء',
        ], $extra));
    }

    private function batchQty(): int
    {
        return (int) Tenancy::bypass(fn () => ProductBatch::where('product_id', $this->product->id)
            ->where('warehouse_id', Stock::warehouseFor($this->branch->id))->where('batch_number', 'B-1')->value('quantity'));
    }

    public function test_return_on_unpaid_invoice_lowers_what_we_owe_and_stock(): void
    {
        [$purchase, $item] = $this->invoice();
        $this->assertEquals(100, (float) $this->reload($this->supplier)->balance);

        $this->returnItems($purchase, $item, 6)->assertSessionHasNoErrors();

        // المخزون: الفرع والدفعة
        $this->assertSame(14, $this->branchQty($this->product, $this->branch));
        $this->assertSame(14, $this->batchQty());
        $this->assertTrue(Tenancy::bypass(fn () => InventoryTransaction::where('type', 'purchase_return')->where('qty_out', 6)->exists()));

        // المورد: 100 − 30
        $this->assertEquals(70, (float) $this->reload($this->supplier)->balance);
        $row = Tenancy::bypass(fn () => SupplierAccount::where('supplier_id', $this->supplier->id)->latest('id')->first());
        $this->assertSame('purchase_return', $row->transaction_type);
        $this->assertEquals(30, (float) $row->debit);

        // الفاتورة: صافيها 70 والمتبقي 70، والخزينة لم تتغير
        $purchase = $this->reload($purchase);
        $this->assertEquals(30, (float) $purchase->returned_amount);
        $this->assertEquals(70, $purchase->remaining());
        $this->assertSame(6, (int) $this->reload($item)->returned_quantity);
        $this->assertEquals(1000, (float) $this->reload($this->register)->current_balance);

        // القيد: من ذمم الموردين إلى مردودات المشتريات
        $return = Tenancy::bypass(fn () => PurchaseReturn::latest('id')->firstOrFail());
        $entry = Tenancy::bypass(fn () => JournalEntry::with('lines')->where('ref_type', 'purchase_return')->where('ref_id', $return->id)->firstOrFail());
        $this->assertEquals(30, (float) $entry->lines->sum('debit'));
        $this->assertEquals(30, (float) $entry->lines->sum('credit'));
    }

    public function test_supplier_payment_respects_the_return(): void
    {
        [$purchase, $item] = $this->invoice();
        $this->returnItems($purchase, $item, 6)->assertSessionHasNoErrors();

        // المستحق الآن 70: دفع 100 مرفوض
        $this->actingAs($this->owner)->post('/supplier-payments', [
            'supplier_id' => $this->supplier->id, 'amount' => 100, 'payment_method' => 'cash', 'cash_register_id' => $this->register->id,
        ])->assertSessionHasErrors('amount');

        $this->actingAs($this->owner)->post('/supplier-payments', [
            'supplier_id' => $this->supplier->id, 'amount' => 70, 'payment_method' => 'cash', 'cash_register_id' => $this->register->id,
        ])->assertSessionHasNoErrors();

        $this->assertEquals(0, (float) $this->reload($this->supplier)->balance);
        $this->assertSame('paid', $this->reload($purchase)->status);
    }

    public function test_return_on_paid_invoice_with_cash_refund(): void
    {
        [$purchase, $item] = $this->invoice();
        $this->actingAs($this->owner)->post("/purchases/{$purchase->id}/pay", [
            'amount' => 100, 'payment_method' => 'cash', 'cash_register_id' => $this->register->id,
        ])->assertSessionHasNoErrors();
        $this->assertEquals(900, (float) $this->reload($this->register)->current_balance);

        $this->returnItems($this->reload($purchase), $item, 4, ['excess_mode' => 'cash', 'cash_register_id' => $this->register->id])
            ->assertSessionHasNoErrors();

        // المورد ردّ 20 نقداً: الخزينة 920، وما علينا يبقى 0
        $this->assertEquals(920, (float) $this->reload($this->register)->current_balance);
        $this->assertEquals(0, (float) $this->reload($this->supplier)->balance);
        $this->assertSame('supplier_refund', Tenancy::bypass(fn () => CashMovement::where('cash_register_id', $this->register->id)->latest('id')->value('type')));

        $purchase = $this->reload($purchase);
        $this->assertEquals(80, (float) $purchase->paid_amount);
        $this->assertEquals(80, $purchase->netTotal());
        $this->assertSame('paid', $purchase->status);
    }

    public function test_return_on_paid_invoice_as_credit_with_supplier(): void
    {
        [$purchase, $item] = $this->invoice();
        $this->actingAs($this->owner)->post("/purchases/{$purchase->id}/pay", [
            'amount' => 100, 'payment_method' => 'bank',
        ])->assertSessionHasNoErrors();

        $this->returnItems($this->reload($purchase), $item, 4, ['excess_mode' => 'credit'])->assertSessionHasNoErrors();

        // رصيد لنا عند المورد 20
        $this->assertEquals(-20, (float) $this->reload($this->supplier)->balance);
        $this->assertEquals(1000, (float) $this->reload($this->register)->current_balance);
        $this->assertEquals(20, (float) Tenancy::bypass(fn () => PurchaseReturn::latest('id')->value('supplier_credit')));
    }

    public function test_cannot_return_what_was_already_sold(): void
    {
        [$purchase, $item] = $this->invoice();
        $cashier = $this->makeCashier($this->org, $this->branch);
        $this->sellViaPos($cashier, [[$this->product, 15]], 150);

        // بقي 5 فقط في الدفعة
        $this->returnItems($purchase, $item, 8)->assertSessionHasErrors('items');
        $this->assertSame(5, $this->branchQty($this->product, $this->branch));
        $this->assertEquals(100, (float) $this->reload($this->supplier)->balance);
    }

    public function test_cannot_return_more_than_bought_and_items_lock_after_return(): void
    {
        [$purchase, $item] = $this->invoice();
        $this->returnItems($purchase, $item, 25)->assertSessionHasErrors('items');

        $this->returnItems($purchase, $item, 2)->assertSessionHasNoErrors();

        // السطر الذي أُرجع منه لا يُعدَّل ولا يُحذف، والفاتورة لا تُحذف
        $this->actingAs($this->owner)->delete("/purchase-items/{$item->id}")->assertSessionHas('error');
        $this->actingAs($this->owner)->delete("/purchases/{$purchase->id}")->assertSessionHas('error');
        $this->assertNotSoftDeleted('purchases', ['id' => $purchase->id]);
    }

    public function test_pages_open_and_cashier_is_denied(): void
    {
        [$purchase, $item] = $this->invoice();
        $this->actingAs($this->owner)->get("/purchases/{$purchase->id}/return")->assertOk()->assertSee($this->product->name);
        $this->returnItems($purchase, $item, 1)->assertSessionHasNoErrors();

        $return = Tenancy::bypass(fn () => PurchaseReturn::latest('id')->firstOrFail());
        $this->actingAs($this->owner)->get('/purchase-returns')->assertOk()->assertSee($return->return_number);
        $this->actingAs($this->owner)->get("/purchase-returns/{$return->id}")->assertOk()->assertSee('قريب الانتهاء');

        $cashier = $this->makeCashier($this->org, $this->branch);
        $this->assertDenied($this->actingAs($cashier)->post("/purchases/{$purchase->id}/return", ['quantities' => [$item->id => 1]]));

        // صيدلية أخرى لا ترى المرتجع
        $other = $this->makeOwner($this->makeOrg());
        $this->assertDenied($this->actingAs($other)->get("/purchase-returns/{$return->id}"));
    }
}
