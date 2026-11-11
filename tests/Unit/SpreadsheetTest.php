<?php

namespace Tests\Unit;

use App\Services\ProductImportService;
use App\Support\Spreadsheet;
use PHPUnit\Framework\TestCase;

class SpreadsheetTest extends TestCase
{
    public function test_xlsx_round_trip_keeps_arabic_and_numbers(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'x') . '.xlsx';
        Spreadsheet::writeXlsx($path, ['الاسم', 'السعر'], [['باراسيتامول & <500>', 1.5]], [], 'ورقة', [0]);

        $rows = Spreadsheet::readXlsx($path);
        @unlink($path);

        $this->assertSame(['الاسم', 'السعر'], $rows[0]);
        $this->assertSame(['باراسيتامول & <500>', '1.5'], $rows[1]);
    }

    public function test_dates_from_excel(): void
    {
        $this->assertSame('2027-03-31', Spreadsheet::parseDate('46477'));
        $this->assertSame('2027-03-31', Spreadsheet::parseDate('31/03/2027'));
        $this->assertSame('2028-03-31', Spreadsheet::parseDate('03/2028'));
        $this->assertSame('2027-05-01', Spreadsheet::parseDate('٢٠٢٧-٠٥-٠١'));
        $this->assertNull(Spreadsheet::parseDate('13/2027'));
    }

    public function test_headers_in_any_order(): void
    {
        [$map] = ProductImportService::mapHeaders(['السعر', 'الاسم', 'سعر الشراء', 'الصلاحية', 'الكمية']);

        $this->assertSame(['price' => 0, 'name' => 1, 'purchase' => 2, 'expiry' => 3, 'qty' => 4], $map);
    }

    public function test_numbers_with_arabic_digits_and_commas(): void
    {
        $this->assertSame(2.5, ProductImportService::number('2,5'));
        $this->assertSame(1250.5, ProductImportService::number('1,250.50'));
        $this->assertSame(12.5, ProductImportService::number('١٢٫٥'));
        $this->assertNull(ProductImportService::number('abc'));
    }
}
