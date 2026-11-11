<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Validation\ValidationException;

/**
 * منطق الفواتير المشترك: حالة الدفع، ربط المتبقي بدين العميل، وحركة المخزون.
 */
class SaleService
{
    /**
     * صافي الفاتورة بعد المرتجعات
     */
    public static function netTotal(Sale $sale): float
    {
        return round(max(0, (float) $sale->total - (float) $sale->returned_amount), 2);
    }

    public static function remaining(Sale $sale): float
    {
        return round(max(0, self::netTotal($sale) - (float) $sale->paid_amount), 2);
    }

    public static function statusFor(float $total, float $paid): string
    {
        if ($total <= 0 || $paid >= $total) {
            return 'paid';
        }

        return $paid > 0 ? 'partial' : 'unpaid';
    }

    /**
     * إعادة حساب الإجمالي من العناصر ثم تحديث الحالة وتسوية دين العميل
     */
    public static function recalculate(Sale $sale): void
    {
        $oldRemaining = self::remaining($sale);

        $total = round((float) $sale->items()->sum('total') - (float) $sale->discount, 2);
        $sale->total = max(0, $total);
        $sale->status = self::statusFor(self::netTotal($sale), (float) $sale->paid_amount);
        $sale->save();

        self::syncDebt($sale, $sale->customer_id ? (int) $sale->customer_id : null, $oldRemaining);
    }

    /**
     * تسوية دين العميل بعد أي تغيير على الفاتورة
     */
    public static function syncDebt(Sale $sale, ?int $oldCustomerId, float $oldRemaining): void
    {
        $newRemaining = self::remaining($sale);
        $newCustomerId = $sale->customer_id ? (int) $sale->customer_id : null;
        $ref = "فاتورة رقم {$sale->invoice_number}";

        if ($oldCustomerId && $oldCustomerId !== $newCustomerId && $oldRemaining > 0) {
            self::reduceDebt($oldCustomerId, $oldRemaining, "إلغاء متبقي {$ref} (تغيير العميل)");
            $oldRemaining = 0;
        }

        if (!$newCustomerId) {
            return;
        }

        $diff = round($newRemaining - ($oldCustomerId === $newCustomerId ? $oldRemaining : 0), 2);

        if ($diff > 0) {
            CustomerLedger::addDebt($newCustomerId, $diff, "زيادة متبقي {$ref}", 'sale_invoice');
        } elseif ($diff < 0) {
            self::reduceDebt($newCustomerId, abs($diff), "تخفيض متبقي {$ref}");
        }
    }

    /**
     * تخفيض دين العميل بحد أقصى رصيده الحالي (لا يصبح الرصيد سالباً)
     */
    public static function reduceDebt(int $customerId, float $amount, string $notes, string $type = 'sale_adjustment'): void
    {
        $balance = (float) Customer::whereKey($customerId)->value('balance');
        $amount = min($amount, $balance);

        if ($amount > 0) {
            CustomerLedger::recordPayment($customerId, $amount, $notes, $type);
        }
    }

    /**
     * خصم كمية من المخزون مع التحقق من التوفر
     */
    public static function takeStock(int $productId, float $quantity): void
    {
        $product = Product::whereKey($productId)->lockForUpdate()->firstOrFail();
        $qty = (int) round($quantity);

        if ($product->stock_quantity < $qty) {
            throw ValidationException::withMessages([
                'quantity' => "الكمية المطلوبة للمنتج ({$product->name}) تتجاوز المخزون المتاح (المتاح: {$product->stock_quantity}).",
            ]);
        }

        $product->decrement('stock_quantity', $qty);
    }

    /**
     * إرجاع كمية للمخزون
     */
    public static function returnStock(int $productId, float $quantity): void
    {
        Product::whereKey($productId)->increment('stock_quantity', (int) round($quantity));
    }
}
