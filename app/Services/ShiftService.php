<?php

namespace App\Services;

use App\Models\CashRegister;
use App\Models\CashShift;
use App\Models\Expense;
use App\Models\PurchasePayment;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * الورديات: فتح الوردية يثبّت رصيد الخزينة الافتتاحي، والإغلاق يقارن ما عدّه الكاشير
 * برصيد النظام، ويسجل الفرق (زيادة/عجز) بقيد محاسبي ويضبط رصيد الخزينة على الفعلي.
 */
class ShiftService
{
    public static function open(CashRegister $register, User $user, ?string $notes = null): CashShift
    {
        return DB::transaction(function () use ($register, $user, $notes) {
            $register = CashRegister::whereKey($register->id)->lockForUpdate()->firstOrFail();

            if ($register->status !== 'active') {
                throw ValidationException::withMessages(['cash_register_id' => 'الخزينة المختارة غير نشطة.']);
            }
            if (CashShift::where('cash_register_id', $register->id)->where('status', 'open')->exists()) {
                throw ValidationException::withMessages(['cash_register_id' => 'هذه الخزينة عليها وردية مفتوحة. يجب إغلاقها أولاً.']);
            }
            if (CashShift::where('user_id', $user->id)->where('status', 'open')->exists()) {
                throw ValidationException::withMessages(['cash_register_id' => 'لديك وردية مفتوحة بالفعل. أغلقها قبل فتح وردية جديدة.']);
            }

            return CashShift::create([
                'cash_register_id' => $register->id,
                'user_id'          => $user->id,
                'opened_at'        => now(),
                'opening_balance'  => $register->current_balance,
                'status'           => 'open',
                'notes'            => $notes,
            ]);
        });
    }

    public static function close(CashShift $shift, float $actual, User $closer, ?string $notes = null): CashShift
    {
        return DB::transaction(function () use ($shift, $actual, $closer, $notes) {
            $shift = CashShift::whereKey($shift->id)->lockForUpdate()->firstOrFail();
            if (!$shift->isOpen()) {
                throw ValidationException::withMessages(['actual_balance' => 'هذه الوردية مغلقة بالفعل.']);
            }

            $register = CashRegister::whereKey($shift->cash_register_id)->lockForUpdate()->firstOrFail();
            $expected = round((float) $register->current_balance, 2);
            $actual = round($actual, 2);
            $difference = round($actual - $expected, 2);

            if ($difference != 0) {
                $orgId = (int) (\App\Models\Branch::whereKey($register->branch_id)->value('organization_id') ?? \App\Models\Organization::value('id'));
                $cash = AccountingService::systemAccount(AccountingService::CASH, $orgId);
                $label = "فرق إغلاق وردية رقم {$shift->id} - خزينة {$register->name}";

                $lines = $difference > 0
                    ? [
                        ['account_id' => $cash->id, 'debit' => $difference, 'credit' => 0],
                        ['account_id' => AccountingService::systemAccount(AccountingService::OTHER_INCOME, $orgId)->id, 'debit' => 0, 'credit' => $difference],
                    ]
                    : [
                        ['account_id' => AccountingService::systemAccount(AccountingService::OTHER_EXPENSES, $orgId)->id, 'debit' => abs($difference), 'credit' => 0],
                        ['account_id' => $cash->id, 'debit' => 0, 'credit' => abs($difference)],
                    ];

                AccountingService::post($orgId, $label, $lines, AccountingService::nextEntryNumber('SH'), null, 'cash_shift', $shift->id);

                $register->current_balance = $actual;
                $register->save();
            }

            $shift->update([
                'closed_at'        => now(),
                'closed_by'        => $closer->id,
                'expected_balance' => $expected,
                'actual_balance'   => $actual,
                'difference'       => $difference,
                'status'           => 'closed',
                'notes'            => trim(($shift->notes ? $shift->notes . "\n" : '') . ($notes ?? '')) ?: null,
            ]);

            return $shift;
        });
    }

    /**
     * ملخص حركة الخزينة خلال الوردية
     */
    public static function summary(CashShift $shift): array
    {
        $registerId = $shift->cash_register_id;
        $from = $shift->opened_at;
        $to = $shift->closed_at ?? now();
        $between = fn ($q, $col = 'created_at') => $q->where($col, '>=', $from)->where($col, '<=', $to);

        $sales = $between(Sale::where('cash_register_id', $registerId))
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(paid_amount), 0) as paid, COALESCE(SUM(total), 0) as total')
            ->first();
        $refunds = (float) $between(SaleReturn::where('cash_register_id', $registerId))->sum('refund_amount');
        $returnsCount = $between(SaleReturn::where('cash_register_id', $registerId))->count();
        $expenses = (float) $between(Expense::where('cash_register_id', $registerId))->sum('amount');
        $supplierPayments = (float) $between(PurchasePayment::where('cash_register_id', $registerId))->sum('amount');

        $opening = (float) $shift->opening_balance;
        $expected = $shift->isOpen()
            ? (float) CashRegister::whereKey($registerId)->value('current_balance')
            : (float) $shift->expected_balance;

        // ما لا يظهر في البنود السابقة: تحصيل ديون، سندات قبض/صرف، دفعات على فواتير قديمة
        $other = round($expected - $opening - (float) $sales->paid + $refunds + $expenses + $supplierPayments, 2);

        return [
            'opening'           => $opening,
            'sales_count'       => (int) $sales->cnt,
            'sales_total'       => (float) $sales->total,
            'cash_sales'        => (float) $sales->paid,
            'returns_count'     => $returnsCount,
            'refunds'           => $refunds,
            'expenses'          => $expenses,
            'supplier_payments' => $supplierPayments,
            'other'             => $other,
            'expected'          => $expected,
        ];
    }
}
