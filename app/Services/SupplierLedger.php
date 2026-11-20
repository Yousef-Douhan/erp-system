<?php

namespace App\Services;

use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\SupplierAccount;
use Illuminate\Support\Facades\DB;

/**
 * ما علينا للمورد. أي تغيير في إجمالي فاتورة شراء يمر من هنا:
 * يُحدَّث رصيد المورد، ويُكتب سطر في كشف حسابه، وقيد: من حـ/ المشتريات إلى حـ/ ذمم الموردين.
 * (الدفعات للمورد تمر من SupplierPaymentService وتعمل العكس.)
 */
class SupplierLedger
{
    /**
     * @param float $oldTotal إجمالي الفاتورة قبل التغيير (0 لفاتورة جديدة)
     * @param float|null $newTotal الإجمالي الجديد (null = الحالي في الفاتورة، 0 عند الحذف)
     */
    public static function syncPurchase(Purchase $purchase, float $oldTotal, ?float $newTotal = null, ?int $supplierId = null): void
    {
        $newTotal ??= (float) $purchase->total;
        $delta = round($newTotal - $oldTotal, 2);
        if (abs($delta) < 0.01) {
            return;
        }

        DB::transaction(function () use ($purchase, $delta, $supplierId) {
            $supplier = Supplier::withoutGlobalScope('tenant')->whereKey($supplierId ?? $purchase->supplier_id)->lockForUpdate()->first();
            if ($supplier) {
                $supplier->balance = round((float) $supplier->balance + $delta, 2);
                $supplier->save();

                SupplierAccount::create([
                    'supplier_id'      => $supplier->id,
                    'transaction_type' => $delta > 0 ? 'purchase_invoice' : 'purchase_adjustment',
                    'debit'            => $delta < 0 ? abs($delta) : 0,
                    'credit'           => $delta > 0 ? $delta : 0,
                    'balance'          => $supplier->balance,
                ]);
            }

            $orgId = (int) $purchase->organization_id;
            if (!$orgId) {
                return;
            }
            $purchases = AccountingService::systemAccount(AccountingService::PURCHASES, $orgId)->id;
            $payables = AccountingService::systemAccount(AccountingService::PAYABLES, $orgId)->id;
            $amount = abs($delta);

            AccountingService::post($orgId, ($delta > 0 ? 'فاتورة شراء ' : 'تخفيض فاتورة شراء ') . $purchase->invoice_number, $delta > 0
                ? [['account_id' => $purchases, 'debit' => $amount, 'credit' => 0], ['account_id' => $payables, 'debit' => 0, 'credit' => $amount]]
                : [['account_id' => $payables, 'debit' => $amount, 'credit' => 0], ['account_id' => $purchases, 'debit' => 0, 'credit' => $amount]],
                AccountingService::nextEntryNumber('PI'), null, 'purchase', $purchase->id);
        });
    }

    /** أسماء أنواع الحركات في كشف المورد */
    public static function label(string $type): string
    {
        return [
            'purchase_invoice'    => 'فاتورة شراء',
            'purchase_adjustment' => 'تعديل فاتورة شراء',
            'purchase_payment'    => 'دفعة للمورد',
            'purchase_return'     => 'مرتجع للمورد',
            'payment_voucher'     => 'سند صرف',
            'opening'             => 'رصيد افتتاحي',
        ][$type] ?? $type;
    }
}
