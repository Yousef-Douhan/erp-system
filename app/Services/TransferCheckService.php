<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\TransferCheck;

/**
 * تسجيل كل مبلغ يمر عن طريق بنك/محفظة في قائمة «مطابقة الحوالات» بحالة «بانتظار التأكيد».
 */
class TransferCheckService
{
    /**
     * @param array{wallet_phone?: ?string, payer_name?: ?string, payer_account?: ?string, transfer_ref?: ?string} $payer بيانات المحوِّل (الافتراضي من الفاتورة)
     */
    public static function record(Sale $sale, float $amount, string $method, string $source, string $direction = 'in', array $payer = []): ?TransferCheck
    {
        $amount = round($amount, 2);
        if ($amount <= 0 || in_array($method, ['cash', 'debt'], true)) {
            return null;
        }

        return TransferCheck::create([
            'organization_id' => $sale->organization_id,
            'branch_id'       => $sale->branch_id,
            'sale_id'         => $sale->id,
            'customer_id'     => $sale->customer_id,
            'user_id'         => auth()->id() ?? $sale->employee_id,
            'source'          => $source,
            'direction'       => $direction,
            'payment_method'  => $method,
            'wallet_phone'    => self::clean($payer['wallet_phone'] ?? $sale->wallet_phone),
            'payer_name'      => self::clean($payer['payer_name'] ?? $sale->payer_name ?? $sale->customer?->name),
            'payer_account'   => self::clean($payer['payer_account'] ?? $sale->payer_account),
            'transfer_ref'    => self::clean($payer['transfer_ref'] ?? $sale->transfer_ref),
            'amount'          => $amount,
            'status'          => 'pending',
        ]);
    }

    /** أرقام عربية → إنجليزية، ومسافات زائدة */
    public static function clean(?string $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $v = strtr(trim(preg_replace('/\s+/u', ' ', $v)), ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);

        return $v === '' ? null : $v;
    }
}
