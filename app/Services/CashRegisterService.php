<?php

namespace App\Services;

use App\Models\CashRegister;
use App\Models\CashShift;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * اختيار الخزينة المناسبة لأي حركة نقدية:
 * الخزينة المحددة ← خزينة وردية المستخدم المفتوحة ← خزينة نشطة في الفرع ← أي خزينة نشطة.
 */
class CashRegisterService
{
    public static function openShiftFor(?User $user): ?CashShift
    {
        if (!$user) {
            return null;
        }

        return CashShift::with('cashRegister')
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();
    }

    /**
     * يرجع الخزينة مقفلة للتعديل. إذا طُلب سحب مبلغ يتحقق من كفاية الرصيد.
     */
    public static function resolve(?int $preferredId, ?User $user = null, ?int $branchId = null, float $withdraw = 0): CashRegister
    {
        $register = null;

        if ($preferredId) {
            $register = CashRegister::whereKey($preferredId)->where('status', 'active')->lockForUpdate()->first();
        }

        if (!$register && ($shift = self::openShiftFor($user))) {
            $register = CashRegister::whereKey($shift->cash_register_id)->where('status', 'active')->lockForUpdate()->first();
        }

        if (!$register && $branchId) {
            $register = CashRegister::where('branch_id', $branchId)->where('status', 'active')->lockForUpdate()->first();
        }

        $register ??= CashRegister::where('status', 'active')->lockForUpdate()->first();

        if (!$register) {
            throw ValidationException::withMessages(['cash_register_id' => 'لا توجد خزينة نشطة. أنشئ خزينة من قسم المحاسبة أولاً.']);
        }

        if ($withdraw > 0 && (float) $register->current_balance < $withdraw) {
            throw ValidationException::withMessages([
                'cash_register_id' => 'رصيد الخزينة (' . $register->name . ') لا يكفي: المتاح ' . number_format((float) $register->current_balance, 2) . ' والمطلوب ' . number_format($withdraw, 2) . '.',
            ]);
        }

        return $register;
    }
}
