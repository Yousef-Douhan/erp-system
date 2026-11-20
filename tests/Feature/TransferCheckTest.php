<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\TransferCheck;
use App\Models\TransferStatement;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsPharmacy;
use Tests\TestCase;

/**
 * مطابقة الحوالات:
 * صاحب الصيدلية يرفع المستندات، وموظف المطابقة يطابق (بتاريخ الوصول)، والمطابقة قابلة للتعديل.
 * php artisan test --filter=TransferCheckTest
 */
class TransferCheckTest extends TestCase
{
    use RefreshDatabase;
    use BuildsPharmacy;

    private const REVIEWER = 'مراجع حوالات';

    private Organization $org;
    private Branch $branch;
    private User $owner;
    private User $cashier;
    private User $reviewer;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->org = $this->makeOrg();
        $this->branch = $this->makeBranch($this->org);
        $this->owner = $this->makeOwner($this->org);
        $this->cashier = $this->makeCashier($this->org, $this->branch);
        $this->reviewer = $this->makeUser(self::REVIEWER, $this->org);
        $this->product = $this->makeProduct($this->org, 10, 6);
        $this->stockIn($this->product, $this->branch, 100);
        $this->makeRegister($this->branch, 100);
        PaymentMethod::updateOrCreate(['code' => 'jawwal_test'], ['name' => 'جوال بي', 'is_active' => true, 'requires_wallet' => true]);
    }

    private function walletSale(int $qty = 2, float $paid = 20)
    {
        return $this->sellViaPos($this->cashier, [[$this->product, $qty]], $paid, [
            'payment_method' => 'jawwal_test', 'wallet_phone' => '0599123456', 'payer_name' => 'محمد خالد سعيد',
        ]);
    }

    private function checks()
    {
        return Tenancy::bypass(fn () => TransferCheck::orderBy('id')->get());
    }

    private function uploadDoc(?string $date = null): TransferStatement
    {
        $this->actingAs($this->owner)->post('/transfer-statements', [
            'files' => [UploadedFile::fake()->image('jawwal.png')], 'statement_date' => $date ?? today()->toDateString(),
            'payment_method' => 'jawwal_test', 'note' => 'كشف جوال بي',
        ])->assertSessionHasNoErrors();

        return Tenancy::bypass(fn () => TransferStatement::latest('id')->firstOrFail());
    }

    public function test_wallet_sale_waits_for_matching_and_cash_sale_does_not(): void
    {
        $sale = $this->walletSale();
        $this->sellViaPos($this->cashier, [[$this->product, 1]], 10); // نقدي

        $checks = $this->checks();
        $this->assertCount(1, $checks);
        $this->assertSame('pending', $checks[0]->status);
        $this->assertEquals(20, (float) $checks[0]->amount);
        $this->assertSame((int) $sale->id, (int) $checks[0]->sale_id);
        $this->assertSame('0599123456', $checks[0]->wallet_phone);

        $this->flushSession();
        $r = $this->actingAs($this->reviewer)->get('/transfers')->assertOk()->assertSee($sale->invoice_number)->assertSee('جوال بي');
        $this->assertEquals(20, (float) $r->viewData('byMethod')->first()->pending);
    }

    public function test_owner_uploads_and_reviewer_matches_with_the_document(): void
    {
        $this->walletSale();
        $doc = $this->uploadDoc();
        Storage::disk('local')->assertExists($doc->file_path);
        $check = $this->checks()->first();

        // الموظف يرى المستند ويطابق عليه
        $this->flushSession();
        $this->actingAs($this->reviewer)->get('/transfers')->assertOk()->assertSee('كشف جوال بي');
        $this->actingAs($this->reviewer)->get("/transfer-statements/{$doc->id}")->assertOk();
        $this->actingAs($this->reviewer)->post("/transfers/{$check->id}/confirm", ['statement_id' => $doc->id, 'note' => 'رقم العملية 4471'])
            ->assertSessionHasNoErrors();

        $check = $this->reload($check);
        $this->assertSame('confirmed', $check->status);
        $this->assertSame((int) $this->reviewer->id, (int) $check->checked_by);
        $this->assertSame((int) $doc->id, (int) $check->statement_id);
        $this->assertSame(today()->toDateString(), $check->received_on->toDateString());
        $this->assertFalse($check->arrivedLate());

        // المستند المربوط لا يُحذف
        $this->actingAs($this->owner)->delete("/transfer-statements/{$doc->id}")->assertSessionHas('error');
        $this->assertNotNull(Tenancy::bypass(fn () => TransferStatement::find($doc->id)));
    }

    public function test_reviewer_cannot_upload_or_delete_documents_or_sell(): void
    {
        $doc = $this->uploadDoc();

        $this->assertDenied($this->actingAs($this->reviewer)->post('/transfer-statements', ['files' => [UploadedFile::fake()->image('x.png')], 'statement_date' => today()->toDateString()]));
        $this->assertDenied($this->actingAs($this->reviewer)->delete("/transfer-statements/{$doc->id}"));
        $this->assertDenied($this->actingAs($this->reviewer)->get('/pos'));
        $this->assertDenied($this->actingAs($this->reviewer)->get('/sales'));
        $this->assertDenied($this->actingAs($this->reviewer)->get('/cash-registers'));

        // صاحب الصيدلية يحذف المستند غير المستخدم
        $this->actingAs($this->owner)->delete("/transfer-statements/{$doc->id}")->assertSessionHasNoErrors();
        $this->assertNull(Tenancy::bypass(fn () => TransferStatement::find($doc->id)));
    }

    public function test_transfer_that_arrives_the_next_day(): void
    {
        $this->walletSale();
        $check = $this->checks()->first();
        // الحوالة سُجلت أمس
        Tenancy::bypass(fn () => TransferCheck::whereKey($check->id)->update(['created_at' => now()->subDay()]));

        // أمس: لم تظهر بعد، فتبقى منتظرة وتظهر اليوم في «بانتظار المطابقة»
        $this->flushSession();
        $pending = $this->actingAs($this->reviewer)->get('/transfers')->assertOk()->viewData('checks');
        $this->assertCount(1, $pending);

        // لا يمكن تاريخ وصول قبل يوم الحوالة أو في المستقبل
        $this->actingAs($this->reviewer)->post("/transfers/{$check->id}/confirm", ['received_on' => now()->subDays(3)->toDateString()])->assertSessionHasErrors('received_on');
        $this->actingAs($this->reviewer)->post("/transfers/{$check->id}/confirm", ['received_on' => now()->addDay()->toDateString()])->assertSessionHasErrors('received_on');

        $this->actingAs($this->reviewer)->post("/transfers/{$check->id}/confirm", ['received_on' => today()->toDateString()])->assertSessionHasNoErrors();
        $check = $this->reload($check);
        $this->assertTrue($check->arrivedLate());

        $this->flushSession();
        $this->actingAs($this->reviewer)->get('/transfers?view=day&date=' . now()->subDay()->toDateString())->assertOk()->assertSee('وصلت متأخرة');
    }

    public function test_rejected_transfer_can_be_edited_to_arrived_later(): void
    {
        $this->walletSale();
        $check = $this->checks()->first();

        $this->actingAs($this->reviewer)->post("/transfers/{$check->id}/reject", [])->assertSessionHasErrors('note');
        $this->actingAs($this->reviewer)->post("/transfers/{$check->id}/reject", ['note' => 'غير موجودة في الكشف'])->assertSessionHasNoErrors();
        $this->assertSame('rejected', $this->reload($check)->status);

        // في اليوم التالي وصلت: تعديل
        $doc = $this->uploadDoc();
        $this->actingAs($this->reviewer)->put("/transfers/{$check->id}", ['status' => 'confirmed', 'received_on' => today()->toDateString(), 'statement_id' => $doc->id, 'note' => 'وصلت متأخرة'])
            ->assertSessionHasNoErrors();
        $check = $this->reload($check);
        $this->assertSame('confirmed', $check->status);
        $this->assertSame((int) $doc->id, (int) $check->statement_id);

        // وإرجاعها للانتظار
        $this->actingAs($this->reviewer)->put("/transfers/{$check->id}", ['status' => 'pending'])->assertSessionHasNoErrors();
        $check = $this->reload($check);
        $this->assertSame('pending', $check->status);
        $this->assertNull($check->received_on);
        $this->assertNull($check->statement_id);
    }

    public function test_bulk_match(): void
    {
        $this->walletSale(1, 10);
        $this->walletSale(2, 20);
        $this->walletSale(3, 30);
        $doc = $this->uploadDoc();
        $ids = $this->checks()->pluck('id')->take(2)->all();

        $this->actingAs($this->reviewer)->post('/transfers/bulk', ['ids' => $ids, 'statement_id' => $doc->id, 'received_on' => today()->toDateString()])
            ->assertSessionHasNoErrors();

        $checks = $this->checks();
        $this->assertSame(['confirmed', 'confirmed', 'pending'], $checks->pluck('status')->all());
        $this->assertSame([$doc->id, $doc->id, null], $checks->pluck('statement_id')->map(fn ($v) => $v ? (int) $v : null)->all());
    }

    public function test_bad_file_type_is_rejected(): void
    {
        $this->actingAs($this->owner)->post('/transfer-statements', [
            'files' => [UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream')], 'statement_date' => today()->toDateString(),
        ])->assertSessionHasErrors('files.0');
        $this->assertSame(0, Tenancy::bypass(fn () => TransferStatement::count()));
    }

    public function test_payment_on_invoice_and_wallet_refund_are_tracked(): void
    {
        $customer = $this->makeCustomer($this->org, $this->branch);
        $sale = $this->sellViaPos($this->cashier, [[$this->product, 3]], 0, ['payment_method' => 'debt', 'customer_id' => $customer->id]);
        // بنك/محفظة بدون رقم المحوِّل: مرفوض
        $this->actingAs($this->owner)->post("/sales/{$sale->id}/pay", ['amount' => 30, 'payment_method' => 'jawwal_test'])->assertSessionHasErrors('wallet_phone');
        // assertRedirect يكشف أي خطأ 500 (assertSessionHasNoErrors وحده لا يكشفه)
        $this->actingAs($this->owner)->post("/sales/{$sale->id}/pay", ['amount' => 30, 'payment_method' => 'jawwal_test', 'wallet_phone' => '0597000111', 'transfer_ref' => 'TX-77'])
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertEquals(30, (float) $this->reload($sale)->paid_amount);

        $paid = $this->walletSale(2, 20);
        $this->actingAs($this->owner)->post("/sales/{$paid->id}/return", ['quantities' => [$paid->items()->first()->id => 1], 'restock' => 1])
            ->assertSessionHasNoErrors();

        $checks = $this->checks();
        $this->assertSame(['sale_payment', 'sale', 'refund'], $checks->pluck('source')->all());
        $this->assertSame(['in', 'in', 'out'], $checks->pluck('direction')->all());
        $this->assertEquals([30, 20, 10], $checks->pluck('amount')->map(fn ($v) => (float) $v)->all());
        // دفعة الفاتورة: اسم المحوِّل الافتراضي = العميل، والرقم ورقم العملية كما أُدخلا
        $this->assertSame($customer->name, $checks[0]->payer_name);
        $this->assertSame('0597000111', $checks[0]->wallet_phone);
        $this->assertSame('TX-77', $checks[0]->transfer_ref);
        // الرد للزبون بنفس بيانات المحوِّل الأصلي
        $this->assertSame('محمد خالد سعيد', $checks[2]->payer_name);
    }

    public function test_access_control(): void
    {
        $this->walletSale();
        $check = $this->checks()->first();

        // الكاشير لا يرى ولا يطابق
        $this->assertDenied($this->actingAs($this->cashier)->get('/transfers'));
        $this->assertDenied($this->actingAs($this->cashier)->post("/transfers/{$check->id}/confirm"));

        // مدير الفرع يطابق لكن لا يرفع مستندات
        $manager = $this->makeManager($this->org, $this->branch);
        $this->actingAs($manager)->post("/transfers/{$check->id}/confirm")->assertSessionHasNoErrors();
        $this->assertSame('confirmed', $this->reload($check)->status);
        $this->assertDenied($this->actingAs($manager)->post('/transfer-statements', ['files' => [UploadedFile::fake()->image('x.png')], 'statement_date' => today()->toDateString()]));

        // صيدلية أخرى لا ترى ولا تلمس
        $other = $this->makeOwner($this->makeOrg());
        $this->assertDenied($this->actingAs($other)->put("/transfers/{$check->id}", ['status' => 'pending']));
        $this->actingAs($other)->post('/transfers/bulk', ['ids' => [$check->id]]);
        $this->assertSame((int) $manager->id, (int) $this->reload($check)->checked_by);
        $this->assertSame('confirmed', $this->reload($check)->status);
    }

    public function test_pos_records_payer_details_for_matching(): void
    {
        $this->sellViaPos($this->cashier, [[$this->product, 2]], 20, [
            'payment_method' => 'jawwal_test', 'wallet_phone' => '٠٥٩٩ ٨٨٨ ٧٧٧', 'payer_name' => 'أحمد يوسف',
            'payer_account' => 'PS92 PALS 0000 1234', 'transfer_ref' => '558812',
        ]);

        $sale = Tenancy::bypass(fn () => \App\Models\Sale::latest('id')->first());
        $this->assertSame('أحمد يوسف', $sale->payer_name);
        $this->assertSame('0599 888 777', $sale->wallet_phone, 'الأرقام العربية لم تتحول');
        $check = $this->checks()->first();
        $this->assertSame('أحمد يوسف', $check->payer_name);
        $this->assertSame('PS92 PALS 0000 1234', $check->payer_account);
        $this->assertSame('558812', $check->transfer_ref);
    }

    public function test_payer_name_is_required_without_customer_and_defaults_to_customer(): void
    {
        $this->ensureCash();
        $this->actingAs($this->cashier)->post('/pos/store', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]], 'payment_method' => 'jawwal_test',
            'paid_amount' => 10, 'wallet_phone' => '0599000000',
        ])->assertSessionHasErrors();
        $this->assertCount(0, $this->checks());

        $customer = $this->makeCustomer($this->org, $this->branch);
        $this->sellViaPos($this->cashier, [[$this->product, 1]], 10, ['payment_method' => 'jawwal_test', 'wallet_phone' => '0599000000', 'customer_id' => $customer->id]);
        $this->assertSame($customer->name, $this->checks()->first()->payer_name);
    }

    public function test_reviewer_finds_transfers_by_what_is_on_the_statement(): void
    {
        $this->sellViaPos($this->cashier, [[$this->product, 2]], 20, ['payment_method' => 'jawwal_test', 'wallet_phone' => '0599123456', 'payer_name' => 'محمد خالد سعيد']);
        $this->sellViaPos($this->cashier, [[$this->product, 3]], 30, ['payment_method' => 'jawwal_test', 'wallet_phone' => '0567000999', 'payer_name' => 'ليلى حسن', 'payer_account' => '445566778', 'transfer_ref' => 'REF-9']);

        $find = function (string $q) {
            $this->flushSession();

            return $this->actingAs($this->reviewer)->get('/transfers?q=' . urlencode($q))->assertOk()->viewData('checks')->pluck('payer_name')->all();
        };

        $this->assertSame(['محمد خالد سعيد'], $find('خالد'));             // جزء من الاسم
        $this->assertSame(['محمد خالد سعيد'], $find('+970 599 123 456'));  // الجوال بصيغة الكشف
        $this->assertSame(['ليلى حسن'], $find('445566778'));              // رقم الحساب
        $this->assertSame(['ليلى حسن'], $find('REF-9'));                   // رقم العملية
        $this->assertSame(['ليلى حسن'], $find('30'));                      // المبلغ
        $this->assertSame([], $find('غير موجود'));
    }

    public function test_bank_needs_account_holder_name_and_wallet_needs_name_and_phone(): void
    {
        $bank = PaymentMethod::updateOrCreate(['code' => 'bop_test'], ['name' => 'بنك فلسطين', 'is_active' => true, 'requires_wallet' => true]);
        $this->assertSame('bank', $bank->fresh()->kind);
        $this->assertSame('wallet', PaymentMethod::where('code', 'jawwal_test')->first()->kind);
        $this->ensureCash();
        $this->assertSame('cash', PaymentMethod::where('code', 'cash')->first()->kind);

        $sell = fn (array $extra) => $this->actingAs($this->cashier)->post('/pos/store', array_merge([
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]], 'paid_amount' => 10,
        ], $extra));

        // بنك: بدون اسم صاحب الحساب مرفوض، وبالاسم فقط (بدون جوال) مقبول
        $sell(['payment_method' => 'bop_test'])->assertSessionHasErrors();
        $sell(['payment_method' => 'bop_test', 'payer_name' => 'شركة النور للأدوية'])->assertSessionHasNoErrors();
        $this->assertSame('شركة النور للأدوية', $this->checks()->last()->payer_name);
        $this->assertNull($this->checks()->last()->wallet_phone);

        // محفظة: الاسم وحده لا يكفي
        $this->flushSession();
        $sell(['payment_method' => 'jawwal_test', 'payer_name' => 'سامي'])->assertSessionHasErrors();
        $sell(['payment_method' => 'jawwal_test', 'payer_name' => 'سامي', 'wallet_phone' => '0598000000'])->assertSessionHasNoErrors();
        $this->assertCount(2, $this->checks());
    }
}
