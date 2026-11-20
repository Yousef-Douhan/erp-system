<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\Organization;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPharmacy;
use Tests\TestCase;

/**
 * سداد الموردين من الخزينة: التوزيع على الفواتير من الأقدم، الخصم من الخزينة، كشف المورد.
 * php artisan test --filter=SupplierPaymentTest
 */
class SupplierPaymentTest extends TestCase
{
    use RefreshDatabase;
    use BuildsPharmacy;

    private Organization $org;
    private Branch $branch;
    private Supplier $supplier;
    private CashRegister $safe;
    private User $owner;
    private Purchase $old;
    private Purchase $mid;
    private Purchase $new;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = $this->makeOrg();
        $this->branch = $this->makeBranch($this->org);
        $this->owner = $this->makeOwner($this->org);
        $this->supplier = $this->makeSupplier($this->org);
        $this->safe = $this->makeRegister($this->branch, 5000);

        // ثلاث فواتير: 1000 (دُفع منها 400)، 800، 500 => المستحق 1900
        $this->old = $this->makePurchase(1000, 400, '-20 days');
        $this->mid = $this->makePurchase(800, 0, '-10 days');
        $this->new = $this->makePurchase(500, 0, '-2 days');
        Tenancy::bypass(fn () => $this->supplier->forceFill(['balance' => 1900])->save());
    }

    private function makePurchase(float $total, float $paid, string $when): Purchase
    {
        return Tenancy::bypass(function () use ($total, $paid, $when) {
            $p = Purchase::create([
                'organization_id' => $this->org->id,
                'branch_id'       => $this->branch->id,
                'supplier_id'     => $this->supplier->id,
                'employee_id'     => $this->owner->id,
                'invoice_number'  => 'P-' . uniqid(),
                'total'           => $total,
                'paid_amount'     => $paid,
                'status'          => $paid > 0 ? 'partial' : 'unpaid',
            ]);
            $p->forceFill(['created_at' => now()->modify($when)])->save();

            return $p;
        });
    }

    private function pay(array $data)
    {
        return $this->actingAs($this->owner)->post('/supplier-payments', array_merge([
            'supplier_id'      => $this->supplier->id,
            'payment_method'   => 'cash',
            'cash_register_id' => $this->safe->id,
        ], $data));
    }

    public function test_lump_sum_pays_oldest_invoices_first(): void
    {
        // 1000: 600 تُكمل الأقدم، 400 من الوسطى، والأحدث لا يُمس
        $this->pay(['amount' => 1000])->assertSessionHasNoErrors();

        $this->assertEquals(1000, (float) $this->reload($this->old)->paid_amount);
        $this->assertSame('paid', $this->reload($this->old)->status);
        $this->assertEquals(400, (float) $this->reload($this->mid)->paid_amount);
        $this->assertSame('partial', $this->reload($this->mid)->status);
        $this->assertEquals(0, (float) $this->reload($this->new)->paid_amount);

        $this->assertEquals(4000, (float) $this->reload($this->safe)->current_balance);
        $this->assertEquals(900, (float) $this->reload($this->supplier)->balance);

        // حركة خزينة لكل فاتورة، مربوطة بها
        $moves = Tenancy::bypass(fn () => CashMovement::where('cash_register_id', $this->safe->id)->where('type', 'supplier_payment')->orderBy('id')->get());
        $this->assertSame([$this->old->id, $this->mid->id], $moves->pluck('reference_id')->map(fn ($v) => (int) $v)->all());
        $this->assertEquals([600, 400], $moves->pluck('amount')->map(fn ($v) => (float) $v)->all());
    }

    public function test_paying_full_due_closes_all_invoices(): void
    {
        $this->pay(['amount' => 1900])->assertSessionHasNoErrors();

        foreach ([$this->old, $this->mid, $this->new] as $p) {
            $this->assertSame('paid', $this->reload($p)->status);
        }
        $this->assertEquals(0, (float) $this->reload($this->supplier)->balance);
    }

    public function test_cannot_pay_more_than_due(): void
    {
        $this->pay(['amount' => 2000])->assertSessionHasErrors('amount');

        $this->assertEquals(5000, (float) $this->reload($this->safe)->current_balance);
        $this->assertEquals(400, (float) $this->reload($this->old)->paid_amount);
    }

    public function test_register_must_have_enough_cash(): void
    {
        $drawer = $this->makeRegister($this->branch, 100);

        $this->pay(['amount' => 500, 'cash_register_id' => $drawer->id])->assertSessionHasErrors();

        $this->assertEquals(100, (float) $this->reload($drawer)->current_balance);
        $this->assertEquals(400, (float) $this->reload($this->old)->paid_amount, 'دُفع جزء رغم رفض العملية');
    }

    public function test_bank_payment_does_not_touch_registers(): void
    {
        $this->pay(['amount' => 600, 'payment_method' => 'bank', 'cash_register_id' => null, 'reference' => 'حوالة 123'])
            ->assertSessionHasNoErrors();

        $this->assertSame('paid', $this->reload($this->old)->status);
        $this->assertEquals(5000, (float) $this->reload($this->safe)->current_balance);
    }

    public function test_single_invoice_payment_still_works(): void
    {
        $this->actingAs($this->owner)->post("/purchases/{$this->mid->id}/pay", [
            'amount' => 300, 'payment_method' => 'cash', 'cash_register_id' => $this->safe->id,
        ])->assertSessionHasNoErrors();

        $this->assertEquals(300, (float) $this->reload($this->mid)->paid_amount);
        $this->assertEquals(4700, (float) $this->reload($this->safe)->current_balance);
        $this->assertEquals(1600, (float) $this->reload($this->supplier)->balance);
    }

    public function test_page_shows_dues(): void
    {
        $this->actingAs($this->owner)->get('/supplier-payments')
            ->assertOk()
            ->assertSee($this->supplier->name)
            ->assertSee('1,900.00');
    }

    public function test_other_pharmacy_cannot_pay_this_supplier(): void
    {
        $orgB = $this->makeOrg();
        $branchB = $this->makeBranch($orgB);
        $ownerB = $this->makeOwner($orgB);
        $registerB = $this->makeRegister($branchB, 5000);

        $status = $this->actingAs($ownerB)->post('/supplier-payments', [
            'supplier_id' => $this->supplier->id, 'amount' => 100, 'payment_method' => 'cash', 'cash_register_id' => $registerB->id,
        ])->getStatusCode();

        $this->assertContains($status, [302, 404]);
        $this->assertEquals(400, (float) $this->reload($this->old)->paid_amount);
        $this->assertEquals(5000, (float) $this->reload($registerB)->current_balance);
    }

    public function test_cashier_cannot_pay_suppliers(): void
    {
        $cashier = $this->makeCashier($this->org, $this->branch);

        $this->assertDenied($this->actingAs($cashier)->post('/supplier-payments', [
            'supplier_id' => $this->supplier->id, 'amount' => 100, 'payment_method' => 'cash', 'cash_register_id' => $this->safe->id,
        ]));
        $this->assertEquals(5000, (float) $this->reload($this->safe)->current_balance);
    }
}
