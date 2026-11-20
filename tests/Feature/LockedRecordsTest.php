<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\JournalEntry;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPharmacy;
use Tests\TestCase;

/**
 * السجلات المالية لا تُعدَّل إلا من مسارها الصحيح:
 * الفواتير (مبالغها وأصنافها)، القيود، الحذف بوجود رصيد، وإلزام الوردية.
 * php artisan test --filter=LockedRecordsTest
 */
class LockedRecordsTest extends TestCase
{
    use RefreshDatabase;
    use BuildsPharmacy;

    private Organization $org;
    private Branch $branch;
    private User $owner;
    private User $cashier;
    private Product $product;
    private CashRegister $register;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = $this->makeOrg();
        $this->branch = $this->makeBranch($this->org);
        $this->owner = $this->makeOwner($this->org);
        $this->cashier = $this->makeCashier($this->org, $this->branch);
        $this->product = $this->makeProduct($this->org, 10, 6);
        $this->stockIn($this->product, $this->branch, 50);
        $this->register = $this->makeRegister($this->branch, 100);
    }

    // ---------------- الفواتير ----------------

    public function test_sales_cannot_be_created_outside_the_pos(): void
    {
        $this->assertContains($this->actingAs($this->owner)->post('/sales', [
            'branch_id' => $this->branch->id, 'invoice_number' => 'FAKE-1', 'total' => 500, 'paid_amount' => 500, 'employee_id' => $this->owner->id,
        ])->getStatusCode(), [404, 405]);

        $this->assertSame(0, Tenancy::bypass(fn () => Sale::count()));
        $this->assertEquals(100, (float) $this->reload($this->register)->current_balance);
    }

    public function test_editing_a_sale_cannot_touch_amounts(): void
    {
        $sale = $this->sellViaPos($this->cashier, [[$this->product, 5]], 50);
        $customer = $this->makeCustomer($this->org, $this->branch);

        $this->actingAs($this->owner)->put("/sales/{$sale->id}", [
            'customer_id' => $customer->id, 'wallet_phone' => '0599111222',
            'total' => 5, 'paid_amount' => 0, 'organization_id' => 999, 'branch_id' => 999,
        ])->assertSessionHasNoErrors();

        $sale = $this->reload($sale);
        $this->assertEquals(50, (float) $sale->total);
        $this->assertEquals(50, (float) $sale->paid_amount);
        $this->assertSame((int) $this->org->id, (int) $sale->organization_id);
        $this->assertSame((int) $customer->id, (int) $sale->customer_id);
        $this->assertSame($customer->name, $sale->customer_name);
        $this->assertEquals(0, (float) $this->reload($customer)->balance, 'ربط فاتورة مدفوعة بعميل أضاف ديناً');
    }

    public function test_sale_items_cannot_be_edited_after_the_sale(): void
    {
        $sale = $this->sellViaPos($this->cashier, [[$this->product, 5]], 50);
        $itemId = $sale->items()->value('id');

        $this->assertContains($this->actingAs($this->owner)->post("/sales/{$sale->id}/items", ['product_id' => $this->product->id, 'quantity' => 1])->getStatusCode(), [404, 405]);
        $this->assertContains($this->actingAs($this->owner)->delete("/sale-items/{$itemId}")->getStatusCode(), [404, 405]);

        $this->assertSame(45, $this->branchQty($this->product, $this->branch));
        $this->assertEquals(50, (float) $this->reload($sale)->total);
    }

    public function test_sale_cannot_be_deleted_when_register_is_short(): void
    {
        $sale = $this->sellViaPos($this->cashier, [[$this->product, 5]], 50);  // الخزينة 150

        // سحب المال من الخزينة (إيداع في البنك مثلاً)
        $this->actingAs($this->owner)->post("/cash-registers/{$this->register->id}/withdraw", ['amount' => 120, 'reason' => 'إيداع بنكي'])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->owner)->delete("/sales/{$sale->id}")->assertSessionHasErrors('sale');

        $this->assertNotSoftDeleted('sales', ['id' => $sale->id]);
        $this->assertEquals(30, (float) $this->reload($this->register)->current_balance);
        $this->assertSame(45, $this->branchQty($this->product, $this->branch));
    }

    // ---------------- القيود ----------------

    public function test_system_journal_entries_are_locked_and_reversed_instead(): void
    {
        $sale = $this->sellViaPos($this->cashier, [[$this->product, 2]], 20);
        $entry = Tenancy::bypass(fn () => JournalEntry::with('lines')->where('ref_type', 'sale')->where('ref_id', $sale->id)->firstOrFail());

        // لا حذف
        $this->assertContains($this->actingAs($this->owner)->delete("/journal-entries/{$entry->id}")->getStatusCode(), [404, 405]);
        // لا تعديل لقيد النظام
        $this->actingAs($this->owner)->put("/journal-entries/{$entry->id}", ['entry_date' => '2020-01-01', 'description' => 'تلاعب'])
            ->assertSessionHas('error');
        $this->assertNotSame('تلاعب', $this->reload($entry)->description);

        // القيد العكسي: نفس الحسابات بعكس المدين والدائن
        $this->actingAs($this->owner)->post("/journal-entries/{$entry->id}/reverse")->assertSessionHasNoErrors();
        $reversal = Tenancy::bypass(fn () => JournalEntry::with('lines')->where('ref_type', 'reversal')->where('ref_id', $entry->id)->firstOrFail());

        foreach ($entry->lines as $line) {
            $mirror = $reversal->lines->firstWhere('account_id', $line->account_id);
            $this->assertNotNull($mirror);
            $this->assertEquals((float) $line->debit, (float) $mirror->credit);
            $this->assertEquals((float) $line->credit, (float) $mirror->debit);
        }

        // مرة واحدة فقط
        $this->actingAs($this->owner)->post("/journal-entries/{$entry->id}/reverse")->assertSessionHas('error');
        $this->assertSame(1, Tenancy::bypass(fn () => JournalEntry::where('ref_type', 'reversal')->where('ref_id', $entry->id)->count()));
    }

    public function test_manual_entry_description_can_be_edited(): void
    {
        $accounts = Tenancy::bypass(fn () => [
            \App\Services\AccountingService::systemAccount(\App\Services\AccountingService::CASH, $this->org->id)->id,
            \App\Services\AccountingService::systemAccount(\App\Services\AccountingService::OWNER, $this->org->id)->id,
        ]);

        $this->actingAs($this->owner)->post('/journal-entries', [
            'organization_id' => $this->org->id, 'entry_date' => now()->toDateString(), 'description' => 'قيد يدوي',
            'lines' => [['account_id' => $accounts[0], 'debit' => 10, 'credit' => 0], ['account_id' => $accounts[1], 'debit' => 0, 'credit' => 10]],
        ])->assertSessionHasNoErrors();

        $entry = Tenancy::bypass(fn () => JournalEntry::whereNull('ref_type')->latest('id')->firstOrFail());
        $this->actingAs($this->owner)->put("/journal-entries/{$entry->id}", ['entry_date' => now()->toDateString(), 'description' => 'قيد يدوي مصحح'])
            ->assertSessionHasNoErrors();
        $this->assertSame('قيد يدوي مصحح', $this->reload($entry)->description);

        $this->actingAs($this->owner)->get('/journal-entries')->assertOk()->assertSee('قيد يدوي مصحح');
    }

    // ---------------- الحذف بوجود رصيد ----------------

    public function test_cannot_delete_supplier_branch_or_product_that_still_hold_value(): void
    {
        $supplier = $this->makeSupplier($this->org);
        Tenancy::bypass(fn () => $supplier->forceFill(['balance' => 40])->save());
        $this->actingAs($this->owner)->delete("/suppliers/{$supplier->id}")->assertSessionHas('error');
        $this->assertNotSoftDeleted('suppliers', ['id' => $supplier->id]);

        $this->actingAs($this->owner)->delete("/branches/{$this->branch->id}")->assertSessionHas('error');
        $this->assertNotSoftDeleted('branches', ['id' => $this->branch->id]);

        $this->actingAs($this->owner)->delete("/products/{$this->product->id}")->assertSessionHas('error');
        $this->assertNotSoftDeleted('products', ['id' => $this->product->id]);

        // مورد بدون رصيد وصنف بدون مخزون يُحذفان عادي (حذف ناعم)
        $empty = $this->makeSupplier($this->org);
        $this->actingAs($this->owner)->delete("/suppliers/{$empty->id}")->assertSessionHasNoErrors();
        $this->assertSoftDeleted('suppliers', ['id' => $empty->id]);

        $unused = $this->makeProduct($this->org);
        $this->actingAs($this->owner)->delete("/products/{$unused->id}");
        $this->assertSoftDeleted('products', ['id' => $unused->id]);
    }

    // ---------------- إلزام الوردية ----------------

    public function test_pharmacy_can_require_an_open_shift_before_selling(): void
    {
        $this->actingAs($this->owner)->put("/organizations/{$this->org->id}", [
            'name' => $this->org->name, 'currency' => 'ILS', 'timezone' => 'Asia/Hebron', 'status' => 'active', 'require_shift' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertTrue((bool) $this->reload($this->org)->require_shift);
        $this->ensureCash();

        // بدون وردية: مرفوض
        $this->actingAs($this->cashier)->get('/pos')->assertOk()->assertSee('لا يمكن البيع قبل فتح وردية');
        $this->actingAs($this->cashier)->post('/pos/store', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]], 'payment_method' => 'cash', 'paid_amount' => 10,
        ])->assertSessionHasErrors();
        $this->assertSame(0, Tenancy::bypass(fn () => Sale::count()));

        // بعد فتح الوردية: يبيع
        $this->actingAs($this->cashier)->post('/shifts', ['cash_register_id' => $this->register->id])->assertSessionHasNoErrors();
        $this->sellViaPos($this->cashier, [[$this->product, 1]], 10);
        $this->assertSame(1, Tenancy::bypass(fn () => Sale::count()));
    }

    public function test_shift_is_optional_by_default(): void
    {
        $this->assertFalse((bool) $this->reload($this->org)->require_shift);
        $this->sellViaPos($this->cashier, [[$this->product, 1]], 10);
        $this->assertSame(1, Tenancy::bypass(fn () => Sale::count()));
    }

    // ---------------- الـ API القديم ----------------

    public function test_old_purchase_api_is_gone(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('api.v1.purchases') || collect(\Illuminate\Support\Facades\Route::getRoutes())->contains(fn ($r) => str_contains($r->uri(), 'v1/purchases')));
    }
}
