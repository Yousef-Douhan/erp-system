<?php

namespace Tests\Unit;

use App\Services\ReturnService;
use PHPUnit\Framework\TestCase;

/**
 * توزيع قيمة المرتجع بين تخفيض الدين والرد النقدي
 */
class ReturnSplitTest extends TestCase
{
    public function test_fully_paid_invoice_refunds_everything(): void
    {
        $this->assertSame(['debt' => 0.0, 'refund' => 30.0], ReturnService::split(100, 100, 30));
    }

    public function test_unpaid_invoice_only_reduces_debt(): void
    {
        $this->assertSame(['debt' => 30.0, 'refund' => 0.0], ReturnService::split(100, 0, 30));
    }

    public function test_partial_payment_reduces_debt_first_then_refunds(): void
    {
        // فاتورة 100، دُفع 60، المتبقي 40. مرتجع 50: يلغي الـ40 ويرد 10
        $this->assertSame(['debt' => 40.0, 'refund' => 10.0], ReturnService::split(100, 60, 50));
    }

    public function test_return_smaller_than_remaining_debt(): void
    {
        $this->assertSame(['debt' => 15.0, 'refund' => 0.0], ReturnService::split(100, 60, 15));
    }
}
