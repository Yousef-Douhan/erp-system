<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPharmacy;
use Tests\TestCase;

/**
 * التقارير: أرقام الربح صحيحة، والتصدير يعمل، وكل صيدلية ترى أرقامها فقط.
 * php artisan test --filter=ReportsTest
 */
class ReportsTest extends TestCase
{
    use RefreshDatabase;
    use BuildsPharmacy;

    private Organization $org;
    private Branch $branch;
    private Product $product;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = $this->makeOrg();
        $this->branch = $this->makeBranch($this->org);
        $this->product = $this->makeProduct($this->org, 10, 6);
        $this->stockIn($this->product, $this->branch, 50);
        $this->makeRegister($this->branch);
        $this->owner = $this->makeOwner($this->org);

        // 3 حبات × 10 = 30 إيراد، تكلفتها 3 × 6 = 18، الربح 12
        $this->sellViaPos($this->makeCashier($this->org, $this->branch), [[$this->product, 3]], 30);
    }

    public function test_profit_report_numbers(): void
    {
        $byProduct = collect($this->actingAs($this->owner)->get('/reports/profit')->assertOk()->viewData('byProduct'));
        $row = $byProduct->firstWhere('name', $this->product->name);

        $this->assertNotNull($row, 'الصنف المباع لا يظهر في تقرير الأرباح');
        $this->assertEquals(30, $row['revenue']);
        $this->assertEquals(18, $row['cogs']);
        $this->assertEquals(12, $row['profit']);
        $this->assertGreaterThanOrEqual(0, $row['profit'], 'ربح بالسالب: سعر الشراء أعلى من البيع');
    }

    public function test_reports_export_csv(): void
    {
        foreach (['/reports/profit', '/reports/employees', '/reports/slow-moving'] as $url) {
            $response = $this->actingAs($this->owner)->get($url . '?export=csv');
            $response->assertOk();
            $this->assertStringContainsString('csv', strtolower((string) $response->headers->get('content-type')), "{$url} لا يصدّر CSV");
        }
    }

    public function test_other_pharmacy_does_not_see_these_numbers(): void
    {
        $ownerB = $this->makeOwner($this->makeOrg());

        $byProduct = collect($this->actingAs($ownerB)->get('/reports/profit')->assertOk()->viewData('byProduct'));

        $this->assertNull($byProduct->firstWhere('name', $this->product->name));
    }

    public function test_cashier_cannot_open_reports(): void
    {
        $cashier = $this->makeCashier($this->org, $this->branch);

        $this->assertDenied($this->actingAs($cashier)->get('/reports/profit'));
    }
}
