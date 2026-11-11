<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductImportService;
use App\Support\PermissionCatalog;
use App\Support\Spreadsheet;
use App\Support\Stock;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * php artisan test --filter=ProductImportTest   (على قاعدة erp_system_testing)
 */
class ProductImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_creates_products_and_branch_stock(): void
    {
        $org = Organization::create(['name' => 'صيدلية', 'currency' => 'ILS', 'timezone' => 'Asia/Hebron', 'status' => 'active']);
        $branch = Branch::create(['organization_id' => $org->id, 'name' => 'فرع', 'status' => 'active']);
        $owner = User::create(['organization_id' => $org->id, 'username' => 'owner1', 'email' => 'o@t.local', 'password' => 'secret123', 'is_active' => true]);
        $owner->assignRole(PermissionCatalog::OWNER_ROLE);
        Tenancy::flush();

        $path = tempnam(sys_get_temp_dir(), 'imp') . '.xlsx';
        Spreadsheet::writeXlsx($path, array_values(ProductImportService::COLUMNS), array_merge(ProductImportService::templateRows(), [
            ['قطرة خاطئة', '', '', '', 30, 12, 5, '', '', '', '', '', '', ''], // شراء > بيع: يُرفض
        ]));

        $this->actingAs($owner)
            ->post('/products/import/preview', ['file' => new UploadedFile($path, 'meds.xlsx', null, null, true), 'branch_id' => $branch->id])
            ->assertRedirect();

        $token = basename(collect(glob(storage_path('app/imports/*.json')))->sortByDesc(fn ($f) => filemtime($f))->first(), '.json');

        $this->actingAs($owner)->post('/products/import', ['token' => $token, 'update_prices' => 1])->assertRedirect('/products');

        $this->assertSame(3, Product::withoutGlobalScopes()->where('organization_id', $org->id)->count());
        $para = Product::withoutGlobalScopes()->where('name', 'باراسيتامول 500 ملغ')->first();
        $this->assertSame(200, Stock::quantity($para->id, $branch->id));
        $this->assertSame(1, $para->productUnits()->withoutGlobalScopes()->count());
        $this->assertNull(Product::withoutGlobalScopes()->where('name', 'قطرة خاطئة')->first());
    }
}
