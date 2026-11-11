<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\CustomerTransaction;
use Illuminate\Support\Facades\DB;

/**
 * المصدر الوحيد لتعديل ديون العملاء.
 * كل حركة تُحدّث customers.balance وتُسجَّل في customer_transactions (دفتر الديون)
 * وفي customer_accounts (كشف الحساب المحاسبي) حتى تبقى الشاشتان متطابقتين.
 */
class CustomerLedger
{
    /**
     * إضافة دين على العميل (فاتورة آجلة أو دين يدوي)
     */
    public static function addDebt(Customer|int $customer, float $amount, string $notes, string $type = 'sale'): Customer
    {
        return self::record($customer, abs($amount), $notes, 'add', $type);
    }

    /**
     * تسجيل سداد دفعة من العميل
     */
    public static function recordPayment(Customer|int $customer, float $amount, string $notes, string $type = 'payment'): Customer
    {
        return self::record($customer, -abs($amount), $notes, 'subtract', $type);
    }

    protected static function record(Customer|int $customer, float $signedAmount, string $notes, string $direction, string $type): Customer
    {
        return DB::transaction(function () use ($customer, $signedAmount, $notes, $direction, $type) {
            $id = $customer instanceof Customer ? $customer->id : $customer;
            $customer = Customer::whereKey($id)->lockForUpdate()->firstOrFail();

            $amount = abs($signedAmount);

            if ($direction === 'subtract' && $amount > (float) $customer->balance + 0.001) {
                throw new \InvalidArgumentException(
                    'مبلغ السداد (' . number_format($amount, 2) . ') أكبر من دين العميل الحالي (' . number_format($customer->balance, 2) . ').'
                );
            }

            $customer->balance = round((float) $customer->balance + $signedAmount, 2);
            $customer->save();

            CustomerTransaction::create([
                'customer_id' => $customer->id,
                'type'        => $direction,
                'amount'      => $amount,
                'notes'       => $notes,
            ]);

            CustomerAccount::create([
                'customer_id'      => $customer->id,
                'transaction_type' => $type,
                'debit'            => $direction === 'add' ? $amount : 0,
                'credit'           => $direction === 'subtract' ? $amount : 0,
                'balance'          => $customer->balance,
            ]);

            return $customer;
        });
    }
}
