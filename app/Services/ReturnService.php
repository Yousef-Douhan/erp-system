<?php

namespace App\Services;

use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * مرتجع المبيعات:
 * 1) يتحقق من الكميات القابلة للإرجاع لكل سطر
 * 2) قيمة المرتجع تُحسب بسعر البيع الفعلي بعد خصم السطر وحصته من خصم الفاتورة
 * 3) تُخفَّض أولاً من المتبقي على العميل، والباقي يُرد له نقداً (أو تحويلاً حسب طريقة الدفع)
 * 4) الكميات تعود لنفس الدفعات التي صُرفت منها (إلا إذا كانت تالفة)
 * 5) قيد: من حـ/ مردودات المبيعات  إلى حـ/ الصندوق أو البنك + ذمم العملاء
 */
class ReturnService
{
    /**
     * @param array<int, float|int|string> $quantities  [sale_item_id => الكمية المرتجعة بوحدة البيع]
     */
    public static function create(Sale $sale, array $quantities, bool $restock, ?string $reason, User $user, ?int $registerId = null): SaleReturn
    {
        return DB::transaction(function () use ($sale, $quantities, $restock, $reason, $user, $registerId) {
            $sale = Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();
            $items = SaleItem::where('sale_id', $sale->id)->lockForUpdate()->get()->keyBy('id');

            $itemsSum = (float) $items->sum('total');
            $discountRatio = $itemsSum > 0 ? min(1, (float) $sale->discount / $itemsSum) : 0;

            $lines = [];
            foreach ($quantities as $itemId => $qty) {
                $qty = round((float) $qty, 2);
                if ($qty <= 0) {
                    continue;
                }

                $item = $items[(int) $itemId] ?? null;
                if (!$item) {
                    throw ValidationException::withMessages(['items' => 'أحد الأصناف المختارة لا يتبع هذه الفاتورة.']);
                }

                $name = $item->product->name ?? 'صنف';
                if ($qty > $item->returnableQuantity()) {
                    throw ValidationException::withMessages([
                        'items' => "الكمية المرتجعة من ({$name}) أكبر من المتاح للإرجاع (" . self::fmt($item->returnableQuantity()) . ').',
                    ]);
                }

                $baseQty = $qty * (float) ($item->unit_factor ?: 1);
                if (abs($baseQty - round($baseQty)) > 0.0001) {
                    throw ValidationException::withMessages(['items' => "كمية ({$name}) لا تتوافق مع وحدة البيع."]);
                }

                $unitNet = self::unitNetPrice($item, $discountRatio);
                $lines[] = [
                    'item'     => $item,
                    'qty'      => $qty,
                    'base_qty' => (int) round($baseQty),
                    'price'    => $unitNet,
                    'total'    => round($qty * $unitNet, 2),
                ];
            }

            if (!$lines) {
                throw ValidationException::withMessages(['items' => 'اختر صنفاً واحداً على الأقل وحدد الكمية المرتجعة.']);
            }

            $total = min(round(array_sum(array_column($lines, 'total')), 2), SaleService::netTotal($sale));

            // ---------- توزيع القيمة: تخفيض الدين أولاً ثم الرد النقدي ----------
            $split = self::split(SaleService::netTotal($sale), (float) $sale->paid_amount, $total);
            $debtReduction = $sale->customer_id ? $split['debt'] : 0.0;
            $refund = round($total - $debtReduction, 2);

            // ---------- طريقة الرد: نقدي من الخزينة أو تحويل للبنك/المحفظة ----------
            $method = PaymentMethod::where('code', $sale->payment_method)->first();
            $isCash = !($method && $method->requires_wallet);
            $register = null;
            if ($refund > 0 && $isCash) {
                $register = CashRegisterService::resolve($registerId ?: $sale->cash_register_id, $user, $sale->branch_id, $refund);
            }

            // ---------- المرتجع ----------
            $return = SaleReturn::create([
                'organization_id'  => $sale->organization_id,
                'branch_id'        => $sale->branch_id,
                'sale_id'          => $sale->id,
                'customer_id'      => $sale->customer_id,
                'user_id'          => $user->id,
                'cash_register_id' => $register?->id,
                'return_number'    => 'TMP-' . uniqid('', true),
                'total'            => $total,
                'refund_amount'    => $refund,
                'debt_reduction'   => $debtReduction,
                'restocked'        => $restock,
                'reason'           => $reason,
            ]);
            $return->update(['return_number' => 'RET-' . date('Ymd') . '-' . str_pad($return->id, 6, '0', STR_PAD_LEFT)]);

            foreach ($lines as $line) {
                $returnItem = $return->items()->create([
                    'sale_item_id' => $line['item']->id,
                    'product_id'   => $line['item']->product_id,
                    'quantity'     => $line['qty'],
                    'unit_price'   => $line['price'],
                    'total'        => $line['total'],
                ]);

                $line['item']->increment('returned_quantity', $line['qty']);

                if ($restock) {
                    StockService::restockReturn($line['item'], $line['base_qty'], $returnItem->id);
                }
            }

            // ---------- الفاتورة ----------
            $sale->returned_amount = round((float) $sale->returned_amount + $total, 2);
            $sale->paid_amount = round(max(0, (float) $sale->paid_amount - $refund), 2);
            $sale->status = SaleService::statusFor(SaleService::netTotal($sale), (float) $sale->paid_amount);
            $sale->save();

            // ---------- دين العميل والخزينة ----------
            if ($debtReduction > 0) {
                SaleService::reduceDebt((int) $sale->customer_id, $debtReduction, "مرتجع {$return->return_number} على فاتورة {$sale->invoice_number}", 'sale_return');
            }
            if ($refund > 0 && !$isCash) {
                // المبلغ يُحوَّل للزبون عن طريق البنك/المحفظة: نتأكد لاحقاً أنه حُوِّل فعلاً
                TransferCheckService::record($sale, $refund, (string) $sale->payment_method, 'refund', 'out');
            }
            if ($register) {
                CashBook::out($register, $refund, 'sale_refund', "مرتجع {$return->return_number} من فاتورة {$sale->invoice_number}", 'sale_return', $return->id);
            }

            // ---------- القيد المحاسبي ----------
            $orgId = (int) $sale->organization_id;
            AccountingService::post($orgId, "مرتجع مبيعات {$return->return_number} من فاتورة {$sale->invoice_number}", [
                ['account_id' => AccountingService::systemAccount(AccountingService::SALES_RETURNS, $orgId)->id, 'debit' => $total, 'credit' => 0],
                ['account_id' => AccountingService::systemAccount($isCash ? AccountingService::CASH : AccountingService::BANK, $orgId)->id, 'debit' => 0, 'credit' => $refund],
                ['account_id' => AccountingService::systemAccount(AccountingService::RECEIVABLES, $orgId)->id, 'debit' => 0, 'credit' => $debtReduction],
            ], AccountingService::nextEntryNumber('SR'), null, 'sale_return', $return->id);

            return $return;
        });
    }

    /**
     * سعر وحدة البيع الصافي: بعد خصم السطر وحصته من خصم الفاتورة
     */
    public static function unitNetPrice(SaleItem $item, float $discountRatio): float
    {
        $qty = (float) $item->quantity;
        if ($qty <= 0) {
            return 0;
        }

        return round(((float) $item->total / $qty) * (1 - $discountRatio), 4);
    }

    /**
     * كيف تُوزَّع قيمة المرتجع: الجزء الذي يلغي متبقياً غير مدفوع، والجزء الذي يُرد كمبلغ مدفوع زائد.
     */
    public static function split(float $netTotal, float $paid, float $returnTotal): array
    {
        $oldRemaining = max(0, $netTotal - $paid);
        $newRemaining = max(0, ($netTotal - $returnTotal) - $paid);
        $debt = round($oldRemaining - $newRemaining, 2);

        return ['debt' => $debt, 'refund' => round($returnTotal - $debt, 2)];
    }

    private static function fmt(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }
}
