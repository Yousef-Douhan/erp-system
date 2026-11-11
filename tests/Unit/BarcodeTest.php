<?php

namespace Tests\Unit;

use App\Support\Barcode;
use PHPUnit\Framework\TestCase;

class BarcodeTest extends TestCase
{
    public function test_code128_starts_with_start_b_and_ends_with_stop(): void
    {
        $w = Barcode::widths('P000042');

        $this->assertStringStartsWith('211214', $w); // Start B
        $this->assertStringEndsWith('2331112', $w);  // Stop
    }

    public function test_each_symbol_is_eleven_modules(): void
    {
        $w = Barcode::widths('ABC123');
        $body = substr($w, 0, -7); // بدون رمز التوقف

        foreach (str_split($body, 6) as $symbol) {
            $this->assertSame(11, array_sum(str_split($symbol)));
        }
    }

    public function test_known_checksum(): void
    {
        // "A" في Code128B قيمتها 33، والتحقق = (104 + 33) % 103 = 34 → النمط 131123
        $this->assertSame('211214' . '111323' . '131123' . '2331112', Barcode::widths('A'));
    }

    public function test_numeric_codes_use_set_c(): void
    {
        // "1234" بالمجموعة C: Start C، ثم 12 و 34، والتحقق = (105 + 12 + 2×34) % 103 = 82
        $this->assertSame('211232' . '112232' . '131123' . '121241' . '2331112', Barcode::widths('1234'));

        // عدد فردي من الأرقام: الرقم الأخير بعد التبديل للمجموعة B (الرمز 100)
        $this->assertStringStartsWith('211232', Barcode::widths('6251234567892'));
        $this->assertStringContainsString('114131', Barcode::widths('6251234567892'));
    }

    public function test_non_ascii_is_stripped(): void
    {
        $this->assertSame('ABC', Barcode::sanitize('ABC دواء'));
        $this->assertStringContainsString('<svg', Barcode::svg('6251234567892'));
    }
}
