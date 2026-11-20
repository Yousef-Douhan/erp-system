<?php

namespace Tests\Concerns;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\InventoryTransaction;
use App\Models\Organization;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\Stock;
use App\Support\Tenancy;
use Spatie\Permission\Models\Role;

/**
 * أدوات تجهيز بيانات الاختبارات (بدون factories).
 * كل الأسماء فريدة (uniqid) حتى لا تتعارض الاختبارات حتى لو بقيت بيانات من اختبار سابق.
 */
trait BuildsPharmacy
{
    protected function makeOrg(string $name = 'صيدلية اختبار'): Organization
    {
        return Organization::create(['name' => $name . ' ' . uniqid(), 'currency' => 'ILS', 'timezone' => 'Asia/Hebron', 'status' => 'active']);
    }

    /** فرع جديد (يُنشأ معه مستودعه تلقائياً) */
    protected function makeBranch(Organization $org, string $name = 'فرع'): Branch
    {
        return Branch::create(['organization_id' => $org->id, 'name' => $name . ' ' . uniqid(), 'status' => 'active']);
    }

    protected function makeUser(string $role, ?Organization $org = null, ?Branch $branch = null, array $extra = []): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::create(array_merge([
            'organization_id' => $org?->id,
            'branch_id'       => $branch?->id,
            'username'        => 'u' . uniqid(),
            'email'           => uniqid('u') . '@test.local',
            'password'        => 'secret123',
            'is_active'       => true,
        ], $extra));
        $user->assignRole($role);
        Tenancy::flush();

        return $user;
    }

    protected function makeAdmin(): User
    {
        return $this->makeUser(PermissionCatalog::ADMIN_ROLE);
    }

    protected function makeOwner(Organization $org): User
    {
        return $this->makeUser(PermissionCatalog::OWNER_ROLE, $org);
    }

    protected function makeManager(Organization $org, Branch $branch): User
    {
        return $this->makeUser(PermissionCatalog::BRANCH_MANAGER_ROLE, $org, $branch);
    }

    protected function makeCashier(Organization $org, Branch $branch): User
    {
        return $this->makeUser(PermissionCatalog::CASHIER_ROLE, $org, $branch);
    }

    protected function makeProduct(Organization $org, float $price = 10, float $cost = 6, array $extra = []): Product
    {
        return Product::create(array_merge([
            'organization_id' => $org->id,
            'name'            => 'صنف ' . uniqid(),
            'purchase_price'  => $cost,
            'selling_price'   => $price,
            'stock_quantity'  => 0,
            'alert_quantity'  => 1,
            'is_active'       => true,
            'track_expiry'    => true,
            'is_controlled'   => false,
        ], $extra));
    }

    /**
     * إدخال رصيد افتتاحي لفرع: دفعة في مستودع الفرع + حركة وارد (نفس مسار الاستيراد والتوريد).
     */
    protected function stockIn(Product $product, Branch $branch, int $qty, ?string $expiry = null): ProductBatch
    {
        return Tenancy::bypass(function () use ($product, $branch, $qty, $expiry) {
            $warehouseId = Stock::warehouseFor($branch->id);

            $batch = ProductBatch::withoutGlobalScopes()->create([
                'product_id'    => $product->id,
                'warehouse_id'  => $warehouseId,
                'batch_number'  => 'B' . uniqid(),
                'expiry_date'   => $expiry ?? now()->addYear()->toDateString(),
                'cost_price'    => $product->purchase_price,
                'selling_price' => $product->selling_price,
                'quantity'      => $qty,
            ]);

            InventoryTransaction::create([
                'warehouse_id'   => $warehouseId,
                'product_id'     => $product->id,
                'batch_id'       => $batch->id,
                'type'           => 'opening_stock',
                'reference_type' => 'test',
                'qty_in'         => $qty,
                'qty_out'        => 0,
            ]);

            return $batch;
        });
    }

    /**
     * هل الطلب مرفوض؟ النظام يرد على عدم الصلاحية إما بـ 403/404 أو بتحويل للرئيسية مع رسالة "ليس لديك صلاحية"
     * (app/Exceptions/Handler.php)، والحالتان رفض صحيح.
     */
    protected function isDenied($response): bool
    {
        $status = $response->getStatusCode();
        if (in_array($status, [403, 404], true)) {
            return true;
        }

        return $status === 302 && str_contains((string) session('error'), 'صلاحية');
    }

    protected function assertDenied($response, string $message = ''): void
    {
        $this->assertTrue($this->isDenied($response), ($message ? $message . ': ' : '') . 'المتوقع رفض الطلب، والنتيجة ' . $response->getStatusCode());
    }

    /** قراءة السجل من قاعدة البيانات بدون فلترة الصيدلية/الفرع (للتحقق في الاختبار) */
    protected function reload($model)
    {
        Tenancy::flush();

        return Tenancy::bypass(fn () => $model->fresh());
    }

    protected function branchQty(Product $product, Branch $branch): int
    {
        return Stock::quantity($product->id, $branch->id);
    }

    protected function makeRegister(Branch $branch, float $balance = 100): CashRegister
    {
        return Tenancy::bypass(fn () => CashRegister::create([
            'branch_id'       => $branch->id,
            // uniqid() قد يتكرر إذا استُدعي مرتين في نفس الميكروثانية (حدث على Windows)
            'name'            => 'صندوق ' . str_replace('.', '', uniqid('', true)),
            'opening_balance' => $balance,
            'current_balance' => $balance,
            'status'          => 'active',
        ]));
    }

    protected function makeCustomer(Organization $org, Branch $branch, float $balance = 0): Customer
    {
        return Tenancy::bypass(fn () => Customer::create([
            'organization_id' => $org->id,
            'branch_id'       => $branch->id,
            'name'            => 'عميل ' . uniqid(),
            'phone'           => '059' . random_int(1000000, 9999999),
            'balance'         => $balance,
            'credit_limit'    => 0,
        ]));
    }

    protected function makeSupplier(Organization $org): Supplier
    {
        return Tenancy::bypass(fn () => Supplier::create(['organization_id' => $org->id, 'name' => 'مورد ' . uniqid()]));
    }

    /** طريقة الدفع النقدية مفعّلة (بعض القواعد فيها طرق دفع جاهزة) */
    protected function ensureCash(): void
    {
        PaymentMethod::updateOrCreate(['code' => 'cash'], ['name' => 'نقدي', 'is_active' => true, 'requires_wallet' => false]);
    }

    /**
     * بيع عبر نقطة البيع كما يفعل الكاشير. يرجع الفاتورة المحفوظة.
     *
     * @param array<int, array{0: Product, 1: int}> $lines
     */
    protected function sellViaPos(User $cashier, array $lines, float $paid, array $extra = []): Sale
    {
        $this->ensureCash();
        Tenancy::flush();

        $response = $this->actingAs($cashier)->post('/pos/store', array_merge([
            'items'          => array_map(fn ($l) => ['product_id' => $l[0]->id, 'quantity' => $l[1]], $lines),
            'payment_method' => 'cash',
            'paid_amount'    => $paid,
        ], $extra));

        $response->assertSessionHasNoErrors();
        Tenancy::flush();

        return Tenancy::bypass(fn () => Sale::latest('id')->firstOrFail());
    }
}
