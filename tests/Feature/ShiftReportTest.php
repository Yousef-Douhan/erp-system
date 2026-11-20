<?php

namespace Tests\Feature;

use App\Models\CashShift;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPharmacy;
use Tests\TestCase;

/**
 * تقرير الوردية: يعرض مبيعات وفواتير الكاشير صاحب الوردية فقط.
 * php artisan test --filter=ShiftReportTest
 */
class ShiftReportTest extends TestCase
{
    use RefreshDatabase;
    use BuildsPharmacy;

    public function test_each_shift_shows_only_its_cashiers_sales(): void
    {
        $org = $this->makeOrg();
        $branch = $this->makeBranch($org);
        $product = $this->makeProduct($org, 10, 6);
        $this->stockIn($product, $branch, 100);
        $reg1 = $this->makeRegister($branch, 100);
        $reg2 = $this->makeRegister($branch, 50);
        $cashier1 = $this->makeCashier($org, $branch);
        $cashier2 = $this->makeCashier($org, $branch);
        $customer = $this->makeCustomer($org, $branch);

        $this->actingAs($cashier1)->post('/shifts', ['cash_register_id' => $reg1->id])->assertSessionHasNoErrors();
        $this->actingAs($cashier2)->post('/shifts', ['cash_register_id' => $reg2->id])->assertSessionHasNoErrors();
        [$shift1, $shift2] = Tenancy::bypass(fn () => [
            CashShift::where('user_id', $cashier1->id)->firstOrFail(),
            CashShift::where('user_id', $cashier2->id)->firstOrFail(),
        ]);

        // كاشير 1: فاتورتين نقداً (30 + 20) وفاتورة آجلة 10
        $s1 = $this->sellViaPos($cashier1, [[$product, 3]], 30);
        $s2 = $this->sellViaPos($cashier1, [[$product, 2]], 20);
        $s3 = $this->sellViaPos($cashier1, [[$product, 1]], 0, ['payment_method' => 'debt', 'customer_id' => $customer->id]);
        // كاشير 2: فاتورة 50
        $s4 = $this->sellViaPos($cashier2, [[$product, 5]], 50);

        // رسالة «تم حفظ الفاتورة» من آخر بيع تبقى في الجلسة وفيها رقم فاتورة كاشير 2
        $this->flushSession();
        $r = $this->actingAs($cashier1)->get("/shifts/{$shift1->id}")->assertOk();
        $mine = $r->viewData('summary')['mine'];
        $this->assertSame(3, $mine['count']);
        $this->assertEquals(60, $mine['net']);
        $this->assertEquals(50, $mine['cash']);
        $this->assertEquals(10, $mine['credit']);
        $this->assertEquals(0, $mine['cash_elsewhere']);
        $this->assertEquals(50, $r->viewData('summary')['cash_sales']);
        $this->assertEquals(150, $r->viewData('summary')['expected']);

        $r->assertSee($s1->invoice_number)->assertSee($s3->invoice_number)->assertDontSee($s4->invoice_number);
        $this->assertCount(3, $r->viewData('invoices'));
        $this->assertSame($product->name, $r->viewData('topProducts')->first()->name);

        // وردية كاشير 2 فيها فاتورته فقط
        $r2 = $this->actingAs($cashier2)->get("/shifts/{$shift2->id}")->assertOk();
        $this->assertSame(1, $r2->viewData('summary')['mine']['count']);
        $r2->assertSee($s4->invoice_number)->assertDontSee($s1->invoice_number);

        // كاشير لا يفتح وردية غيره
        $this->assertDenied($this->actingAs($cashier2)->get("/shifts/{$shift1->id}"));

        // صاحب الصيدلية يرى الاثنتين
        $owner = $this->makeOwner($org);
        $this->actingAs($owner)->get("/shifts/{$shift1->id}")->assertOk()->assertSee($s2->invoice_number);
    }

    public function test_cash_goes_to_the_shift_register_even_if_the_screen_sends_another(): void
    {
        $org = $this->makeOrg();
        $branch = $this->makeBranch($org);
        $product = $this->makeProduct($org, 10, 6);
        $this->stockIn($product, $branch, 20);
        $shiftRegister = $this->makeRegister($branch, 0);
        $otherRegister = $this->makeRegister($branch, 0);
        Tenancy::bypass(function () use ($shiftRegister, $otherRegister) {
            $shiftRegister->update(['name' => 'درج الوردية']);
            $otherRegister->update(['name' => 'الخزنة الرئيسية']);
        });
        $cashier = $this->makeCashier($org, $branch);

        $this->actingAs($cashier)->post('/shifts', ['cash_register_id' => $shiftRegister->id])->assertSessionHasNoErrors();

        // الشاشة أرسلت خزينة أخرى (اختيار قديم محفوظ في المتصفح مثلاً)
        $this->sellViaPos($cashier, [[$product, 3]], 30, ['cash_register_id' => $otherRegister->id]);

        $this->assertEquals(30, (float) $this->reload($shiftRegister)->current_balance);
        $this->assertEquals(0, (float) $this->reload($otherRegister)->current_balance);

        // نقطة البيع تعرض خزينة الوردية فقط
        $this->flushSession();
        // الأسماء داخل JSON مشفّرة (\u...)، فنفحص بيانات الصفحة نفسها
        $registers = $this->actingAs($cashier)->get('/pos')->assertOk()->viewData('registers');
        $this->assertSame([(int) $shiftRegister->id], $registers->pluck('id')->map(fn ($v) => (int) $v)->values()->all());

        // إغلاق الوردية بالمبلغ الصحيح: لا زيادة ولا عجز
        $shift = Tenancy::bypass(fn () => CashShift::where('user_id', $cashier->id)->firstOrFail());
        $this->actingAs($cashier)->post("/shifts/{$shift->id}/close", ['actual_balance' => 30])->assertSessionHasNoErrors();
        $this->assertEquals(0, (float) $this->reload($shift)->difference);
    }

    public function test_closing_refuses_counting_bank_payments_as_cash(): void
    {
        $org = $this->makeOrg();
        $branch = $this->makeBranch($org);
        $product = $this->makeProduct($org, 10, 6);
        $this->stockIn($product, $branch, 20);
        $register = $this->makeRegister($branch, 0);
        $cashier = $this->makeCashier($org, $branch);
        \App\Models\PaymentMethod::updateOrCreate(['code' => 'bank_test'], ['name' => 'بنك', 'is_active' => true, 'requires_wallet' => true]);

        $this->actingAs($cashier)->post('/shifts', ['cash_register_id' => $register->id])->assertSessionHasNoErrors();
        $this->sellViaPos($cashier, [[$product, 3]], 30, ['payment_method' => 'bank_test', 'wallet_phone' => '0599000000', 'payer_name' => 'سامي أحمد']);
        $shift = Tenancy::bypass(fn () => CashShift::where('user_id', $cashier->id)->firstOrFail());

        // نفس حالة وردية #3: المتوقع 0 والكاشير أدخل مبلغ البنك
        $this->actingAs($cashier)->post("/shifts/{$shift->id}/close", ['actual_balance' => 30])->assertSessionHasErrors('actual_balance');
        $this->assertTrue($this->reload($shift)->isOpen());
        $this->assertEquals(0, (float) $this->reload($register)->current_balance);

        // الإغلاق الصحيح
        $this->actingAs($cashier)->post("/shifts/{$shift->id}/close", ['actual_balance' => 0])->assertSessionHasNoErrors();
        $this->assertEquals(0, (float) $this->reload($shift)->difference);
    }

    public function test_real_cash_surplus_can_be_confirmed(): void
    {
        $org = $this->makeOrg();
        $branch = $this->makeBranch($org);
        $product = $this->makeProduct($org, 10, 6);
        $this->stockIn($product, $branch, 20);
        $register = $this->makeRegister($branch, 0);
        $cashier = $this->makeCashier($org, $branch);
        \App\Models\PaymentMethod::updateOrCreate(['code' => 'bank_test'], ['name' => 'بنك', 'is_active' => true, 'requires_wallet' => true]);

        $this->actingAs($cashier)->post('/shifts', ['cash_register_id' => $register->id])->assertSessionHasNoErrors();
        $this->sellViaPos($cashier, [[$product, 3]], 30, ['payment_method' => 'bank_test', 'wallet_phone' => '0599000000', 'payer_name' => 'سامي أحمد']);
        $shift = Tenancy::bypass(fn () => CashShift::where('user_id', $cashier->id)->firstOrFail());

        $this->actingAs($cashier)->post("/shifts/{$shift->id}/close", ['actual_balance' => 30, 'confirm_cash' => 1])->assertSessionHasNoErrors();
        $this->assertEquals(30, (float) $this->reload($shift)->difference);
    }
}
