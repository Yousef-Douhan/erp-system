<?php

namespace Tests\Feature;

use App\Models\CashShift;
use App\Models\JournalEntry;
use App\Models\Sale;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsPharmacy;
use Tests\TestCase;

/**
 * البيع بدون إنترنت: رفع الفواتير المحفوظة على الجهاز، منع التكرار، ومراجعة ما يحتاج نظر المدير.
 * php artisan test --filter=OfflineSyncTest
 */
class OfflineSyncTest extends TestCase
{
    use RefreshDatabase;
    use BuildsPharmacy;

    private function payload($product, int $qty, float $paid, $cashier, array $extra = []): array
    {
        return array_merge([
            'uuid'           => (string) Str::uuid(),
            'number'         => 'OFF-1-261120-001',
            'user_id'        => $cashier->id,
            'at'             => now()->subHours(3)->toIso8601String(),
            'items'          => [['product_id' => $product->id, 'quantity' => $qty]],
            'prices'         => [$product->id . ':0' => (float) $product->selling_price],
            'payment_method' => 'cash',
            'paid_amount'    => $paid,
        ], $extra);
    }

    private function sync($user, array ...$sales)
    {
        $this->ensureCash();
        Tenancy::flush();
        $r = $this->actingAs($user)->postJson('/pos/sync', ['sales' => $sales]);
        Tenancy::flush();

        return $r;
    }

    public function test_offline_sale_is_saved_once_with_its_real_time(): void
    {
        $org = $this->makeOrg();
        $branch = $this->makeBranch($org);
        $product = $this->makeProduct($org, 10, 6);
        $this->stockIn($product, $branch, 20);
        $register = $this->makeRegister($branch, 0);
        $cashier = $this->makeCashier($org, $branch);
        $this->actingAs($cashier)->post('/shifts', ['cash_register_id' => $register->id])->assertSessionHasNoErrors();

        $at = now()->subDay()->setTime(21, 15);
        $sale = $this->payload($product, 3, 30, $cashier, ['at' => $at->toIso8601String()]);

        $r = $this->sync($cashier, $sale)->assertOk();
        $first = $r->json('results.0');
        $this->assertSame('ok', $first['status']);
        $this->assertFalse($first['needs_review']);

        // نفس الفاتورة مرة ثانية (انقطع النت قبل وصول الرد): لا تتكرر
        $again = $this->sync($cashier, $sale)->assertOk()->json('results.0');
        $this->assertSame('duplicate', $again['status']);
        $this->assertSame($first['invoice_number'], $again['invoice_number']);

        $saved = Tenancy::bypass(fn () => Sale::with('items')->get());
        $this->assertCount(1, $saved);
        $s = $saved->first();
        $this->assertSame('OFF-1-261120-001', $s->offline_number);
        $this->assertSame($at->format('Y-m-d H:i'), $s->created_at->format('Y-m-d H:i'));
        $this->assertStringContainsString('INV-' . $at->format('Ymd') . '-', $s->invoice_number);
        $this->assertEquals(30, (float) $s->total);

        // المخزون والخزينة والوردية والقيد بتاريخ البيع
        $this->assertSame(17, $this->branchQty($product, $branch));
        $this->assertEquals(30, (float) $this->reload($register)->current_balance);
        $shift = Tenancy::bypass(fn () => CashShift::where('user_id', $cashier->id)->firstOrFail());
        $this->assertEquals(30, (float) \App\Models\CashMovement::where('cash_shift_id', $shift->id)->sum('amount'));
        $entry = Tenancy::bypass(fn () => JournalEntry::where('ref_type', 'sale')->where('ref_id', $s->id)->firstOrFail());
        $this->assertSame($at->toDateString(), \Illuminate\Support\Carbon::parse($entry->entry_date)->toDateString());
    }

    public function test_device_price_is_kept_and_flagged_when_price_changed(): void
    {
        $org = $this->makeOrg();
        $branch = $this->makeBranch($org);
        $product = $this->makeProduct($org, 12, 6);
        $this->stockIn($product, $branch, 20);
        $this->makeRegister($branch, 0);
        $cashier = $this->makeCashier($org, $branch);

        // الجهاز كان عنده السعر القديم 10
        $sale = $this->payload($product, 2, 20, $cashier, ['prices' => [$product->id . ':0' => 10]]);
        $res = $this->sync($cashier, $sale)->assertOk()->json('results.0');

        $this->assertSame('ok', $res['status']);
        $this->assertTrue($res['needs_review']);
        $s = Tenancy::bypass(fn () => Sale::firstOrFail());
        $this->assertEquals(20, (float) $s->total);
        $this->assertStringContainsString('سعر', $s->review_note);
    }

    public function test_shortage_and_credit_limit_are_accepted_and_flagged(): void
    {
        $org = $this->makeOrg();
        $branch = $this->makeBranch($org);
        $product = $this->makeProduct($org, 10, 6);
        $this->stockIn($product, $branch, 2);
        $this->makeRegister($branch, 0);
        $cashier = $this->makeCashier($org, $branch);
        $customer = $this->makeCustomer($org, $branch, 40);
        Tenancy::bypass(fn () => $customer->forceFill(['credit_limit' => 50])->save());

        // بيع 5 والمسجل 2، بالدين والعميل قريب من حده
        $sale = $this->payload($product, 5, 0, $cashier, ['payment_method' => 'debt', 'customer_id' => $customer->id]);
        $res = $this->sync($cashier, $sale)->assertOk()->json('results.0');

        $this->assertSame('ok', $res['status']);
        $this->assertTrue($res['needs_review']);
        $this->assertStringContainsString('المخزون', $res['review_note']);
        $this->assertStringContainsString('الائتماني', $res['review_note']);
        $this->assertEquals(90, (float) $this->reload($customer)->balance);
        $this->assertSame(-3, $this->branchQty($product, $branch));
    }

    public function test_only_the_seller_can_upload_their_sales(): void
    {
        $org = $this->makeOrg();
        $branch = $this->makeBranch($org);
        $product = $this->makeProduct($org, 10, 6);
        $this->stockIn($product, $branch, 20);
        $this->makeRegister($branch, 0);
        $cashier1 = $this->makeCashier($org, $branch);
        $cashier2 = $this->makeCashier($org, $branch);

        $res = $this->sync($cashier2, $this->payload($product, 1, 10, $cashier1))->assertOk()->json('results.0');
        $this->assertSame('other_user', $res['status']);
        $this->assertSame(0, Tenancy::bypass(fn () => Sale::count()));
    }

    public function test_future_device_clock_and_bad_rows_do_not_break_the_batch(): void
    {
        $org = $this->makeOrg();
        $branch = $this->makeBranch($org);
        $product = $this->makeProduct($org, 10, 6);
        $this->stockIn($product, $branch, 20);
        $this->makeRegister($branch, 0);
        $cashier = $this->makeCashier($org, $branch);

        $future = $this->payload($product, 1, 10, $cashier, ['at' => now()->addDays(2)->toIso8601String()]);
        $cashWithoutCustomer = $this->payload($product, 1, 0, $cashier);   // ناقص الدفع بدون عميل = مرفوض
        $good = $this->payload($product, 1, 10, $cashier);

        $results = $this->sync($cashier, $future, $cashWithoutCustomer, $good)->assertOk()->json('results');

        $this->assertSame(['ok', 'rejected', 'ok'], array_column($results, 'status'));
        $this->assertTrue($results[0]['needs_review']);
        $this->assertSame(2, Tenancy::bypass(fn () => Sale::count()));
        $this->assertTrue(Tenancy::bypass(fn () => Sale::where('client_uuid', $future['uuid'])->firstOrFail())->created_at->lte(now()->addMinute()));
    }

    public function test_ping_returns_a_fresh_token_and_review_page_works(): void
    {
        $org = $this->makeOrg();
        $branch = $this->makeBranch($org);
        $product = $this->makeProduct($org, 12, 6);
        $this->stockIn($product, $branch, 20);
        $this->makeRegister($branch, 0);
        $cashier = $this->makeCashier($org, $branch);
        $owner = $this->makeOwner($org);

        $this->actingAs($cashier)->getJson('/pos/ping')->assertOk()->assertJson(['ok' => true, 'user_id' => $cashier->id])->assertJsonStructure(['token']);

        $this->sync($cashier, $this->payload($product, 1, 10, $cashier, ['prices' => [$product->id . ':0' => 10]]))->assertOk();
        $sale = Tenancy::bypass(fn () => Sale::firstOrFail());

        // الكاشير لا يراجع
        $this->assertDenied($this->actingAs($cashier)->get('/offline-sales'));

        $page = $this->actingAs($owner)->get('/offline-sales')->assertOk();
        $this->assertCount(1, $page->viewData('sales'));
        $this->assertSame(1, $page->viewData('pendingCount'));

        $this->actingAs($owner)->post("/offline-sales/{$sale->id}/reviewed", ['note' => 'السعر الجديد من اليوم'])->assertRedirect();
        $sale = $this->reload($sale);
        $this->assertNotNull($sale->reviewed_at);
        $this->assertSame((int) $owner->id, (int) $sale->reviewed_by);
        $this->assertSame(0, $this->actingAs($owner)->get('/offline-sales')->viewData('pendingCount'));
    }

    public function test_pos_page_carries_offline_settings(): void
    {
        $org = $this->makeOrg();
        $branch = $this->makeBranch($org);
        $cashier = $this->makeCashier($org, $branch);

        $this->actingAs($cashier)->get('/pos')->assertOk()
            ->assertSee('pos-offline.js', false)
            ->assertSee('id="posNet"', false)
            ->assertSee('manifest.json', false)
            ->assertHeader('Cache-Control');
    }
}
