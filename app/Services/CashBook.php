<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashShift;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * دفتر الخزينة: المكان الوحيد الذي يغيّر رصيد أي خزينة.
 * كل تغيير = حركة مسجلة (نوعها، مبلغها، من سجلها، في أي وردية، والرصيد بعدها).
 */
class CashBook
{
    /** أنواع الحركات وأسماؤها في الشاشات */
    public const TYPES = [
        'carried_forward'  => 'رصيد مُرحّل',
        'opening'          => 'رصيد افتتاحي',
        'sale'             => 'مبيعات نقدية',
        'sale_payment'     => 'تحصيل على فاتورة',
        'debt_collection'  => 'تحصيل دين',
        'sale_cancel'      => 'إلغاء فاتورة',
        'sale_refund'      => 'رد نقدي لمرتجع',
        'expense'          => 'مصروف',
        'expense_reversal' => 'إلغاء مصروف',
        'supplier_payment' => 'دفعة لمورد',
        'supplier_refund'  => 'استرداد من مورد (مرتجع مشتريات)',
        'receipt_voucher'  => 'سند قبض',
        'payment_voucher'  => 'سند صرف',
        'deposit'          => 'إيداع',
        'withdrawal'       => 'سحب',
        'transfer_in'      => 'تحويل وارد',
        'transfer_out'     => 'تحويل صادر',
        'shift_surplus'    => 'زيادة عند إغلاق وردية',
        'shift_shortage'   => 'عجز عند إغلاق وردية',
    ];

    /** وارد للخزينة */
    public static function in(CashRegister|int $register, float $amount, string $type, ?string $description = null, ?string $refType = null, ?int $refId = null): ?CashMovement
    {
        return self::record($register, round($amount, 2), 'in', $type, $description, $refType, $refId, false);
    }

    /**
     * صادر من الخزينة. يرفض إذا لم يكفِ الرصيد، إلا إذا $allowNegative (مثل إلغاء فاتورة قديمة).
     */
    public static function out(CashRegister|int $register, float $amount, string $type, ?string $description = null, ?string $refType = null, ?int $refId = null, bool $allowNegative = false): ?CashMovement
    {
        return self::record($register, round($amount, 2), 'out', $type, $description, $refType, $refId, $allowNegative);
    }

    /**
     * تحويل بين خزينتين (مثلاً من درج الكاشير إلى الخزنة الرئيسية آخر اليوم).
     * لا يؤثر على الأرباح ولا على القيود: النقد بقي داخل الصيدلية.
     */
    public static function transfer(CashRegister $from, CashRegister $to, float $amount, ?string $note = null): array
    {
        if ($from->id === $to->id) {
            throw ValidationException::withMessages(['to_register_id' => 'اختر خزينة مختلفة.']);
        }

        return DB::transaction(function () use ($from, $to, $amount, $note) {
            $text = $note ? " ({$note})" : '';
            $out = self::out($from, $amount, 'transfer_out', 'تحويل إلى ' . $to->name . $text, 'cash_register', $to->id);
            $in = self::in($to, $amount, 'transfer_in', 'تحويل من ' . $from->name . $text, 'cash_register', $from->id);

            return [$out, $in];
        });
    }

    private static function record(CashRegister|int $register, float $amount, string $direction, string $type, ?string $description, ?string $refType, ?int $refId, bool $allowNegative): ?CashMovement
    {
        if ($amount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($register, $amount, $direction, $type, $description, $refType, $refId, $allowNegative) {
            $id = $register instanceof CashRegister ? $register->id : $register;
            $locked = \App\Support\Tenancy::bypass(fn () => CashRegister::withTrashed()->whereKey($id)->lockForUpdate()->firstOrFail());

            $balance = (float) $locked->current_balance;
            if ($direction === 'out' && !$allowNegative && $balance + 0.0001 < $amount) {
                throw ValidationException::withMessages([
                    'amount' => 'رصيد الخزينة (' . $locked->name . ') لا يكفي: المتاح ' . number_format($balance, 2) . ' ₪ والمطلوب ' . number_format($amount, 2) . ' ₪.',
                ]);
            }

            $locked->current_balance = round($balance + ($direction === 'in' ? $amount : -$amount), 2);
            $locked->saveQuietly();

            // الموديل الذي بيد المستدعي يبقى متزامناً مع الرصيد الجديد
            if ($register instanceof CashRegister) {
                $register->current_balance = $locked->current_balance;
                $register->syncOriginalAttribute('current_balance');
            }

            $shiftId = \App\Support\Tenancy::bypass(fn () => CashShift::where('cash_register_id', $locked->id)->where('status', 'open')->value('id'));

            return \App\Support\Tenancy::bypass(fn () => CashMovement::create([
                'cash_register_id' => $locked->id,
                'branch_id'        => $locked->branch_id,
                'cash_shift_id'    => $shiftId,
                'user_id'          => auth()->id(),
                'type'             => $type,
                'direction'        => $direction,
                'amount'           => $amount,
                'balance_after'    => $locked->current_balance,
                'reference_type'   => $refType,
                'reference_id'     => $refId,
                'description'      => $description,
            ]));
        });
    }
}
