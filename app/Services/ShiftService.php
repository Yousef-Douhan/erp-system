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

                // الفرق يدخل/يخرج من الخزينة كحركة مسجلة حتى يصبح الرصيد = المعدود فعلاً
                $difference > 0
                    ? CashBook::in($register, $difference, 'shift_surplus', $label, 'cash_shift', $shift->id)
                    : CashBook::out($register, abs($difference), 'shift_shortage', $label, 'cash_shift', $shift->id, true);
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
     * ملخص الوردية:
     * - «mine»: مبيعات الكاشير صاحب الوردية فقط خلال وقتها (كل طرق الدفع: نقدي، بنك/محفظة، آجل)
     * - حركة الخزينة: من دفتر حركات الخزينة المربوطة بالوردية (للورديات القديمة قبل الدفتر: من الفواتير والمرتجعات)
     */
    public static function summary(CashShift $shift): array
    {
        $registerId = $shift->cash_register_id;
        $from = $shift->opened_at;
        $to = $shift->closed_at ?? now();
        $between = fn ($q, $col = 'created_at') => $q->where($col, '>=', $from)->where($col, '<=', $to);

        // ---------- مبيعات الكاشير نفسه ----------
        $mine = self::cashierSales($shift)
            ->selectRaw("COUNT(*) AS cnt,
                COALESCE(SUM(total - COALESCE(returned_amount, 0)), 0) AS net,
                COALESCE(SUM(COALESCE(discount, 0)), 0) AS discount,
                COALESCE(SUM(CASE WHEN cash_register_id IS NOT NULL THEN paid_amount ELSE 0 END), 0) AS cash,
                COALESCE(SUM(CASE WHEN cash_register_id IS NOT NULL AND cash_register_id <> ? THEN paid_amount ELSE 0 END), 0) AS cash_elsewhere,
                COALESCE(SUM(CASE WHEN cash_register_id IS NULL THEN paid_amount ELSE 0 END), 0) AS noncash,
                COALESCE(SUM(GREATEST(total - COALESCE(returned_amount, 0) - paid_amount, 0)), 0) AS credit", [$registerId])
            ->first();
        $myReturns = $between(SaleReturn::where('user_id', $shift->user_id))
            ->selectRaw('COUNT(*) AS cnt, COALESCE(SUM(total), 0) AS total')->first();

        $opening = (float) $shift->opening_balance;
        $expected = $shift->isOpen()
            ? (float) CashRegister::whereKey($registerId)->value('current_balance')
            : (float) $shift->expected_balance;

        // ---------- حركة الخزينة ----------
        $moves = \App\Models\CashMovement::where('cash_shift_id', $shift->id)
            ->whereNotIn('type', ['shift_surplus', 'shift_shortage'])
            ->selectRaw("type, SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END) AS net")
            ->groupBy('type')->pluck('net', 'type')->map(fn ($v) => round((float) $v, 2));

        if ($moves->isNotEmpty()) {
            $cashSales = (float) ($moves['sale'] ?? 0);
            $debts = (float) (($moves['sale_payment'] ?? 0) + ($moves['debt_collection'] ?? 0));
            $refunds = -(float) (($moves['sale_refund'] ?? 0) + ($moves['sale_cancel'] ?? 0));
            $expenses = -(float) (($moves['expense'] ?? 0) + ($moves['expense_reversal'] ?? 0));
            $supplierPayments = -(float) (($moves['supplier_payment'] ?? 0) + ($moves['supplier_refund'] ?? 0));
        } else {
            // ورديات قبل دفتر حركات الخزينة
            $cashSales = (float) $between(Sale::where('cash_register_id', $registerId))->sum('paid_amount');
            $debts = 0.0;
            $refunds = (float) $between(SaleReturn::where('cash_register_id', $registerId))->sum('refund_amount');
            $expenses = (float) $between(Expense::where('cash_register_id', $registerId))->sum('amount');
            $supplierPayments = (float) $between(PurchasePayment::where('cash_register_id', $registerId))->sum('amount');
        }

        // ما لا يظهر في البنود السابقة: سندات قبض/صرف، إيداع/سحب، تحويلات، بيع موظف آخر على نفس الخزينة
        $other = round($expected - $opening - $cashSales - $debts + $refunds + $expenses + $supplierPayments, 2);

        return [
            'opening'           => $opening,
            'sales_count'       => (int) $mine->cnt,
            'sales_total'       => round((float) $mine->net, 2),
            'cash_sales'        => round($cashSales, 2),
            'debts'             => round($debts, 2),
            'returns_count'     => (int) $myReturns->cnt,
            'refunds'           => round($refunds, 2),
            'expenses'          => round($expenses, 2),
            'supplier_payments' => round($supplierPayments, 2),
            'other'             => $other,
            'expected'          => $expected,
            'mine'              => [
                'count'    => (int) $mine->cnt,
                'net'      => round((float) $mine->net, 2),
                'discount' => round((float) $mine->discount, 2),
                'cash'     => round((float) $mine->cash, 2),
                'noncash'  => round((float) $mine->noncash, 2),
                'credit'   => round((float) $mine->credit, 2),
                'cash_elsewhere' => round((float) $mine->cash_elsewhere, 2),   // نقد من مبيعاته دخل خزينة غير خزينة الوردية
                'avg'      => $mine->cnt ? round((float) $mine->net / $mine->cnt, 2) : 0,
                'returns_count' => (int) $myReturns->cnt,
                'returns_total' => round((float) $myReturns->total, 2),
            ],
        ];
    }

    /** فواتير الكاشير صاحب الوردية خلال وقت الوردية */
    public static function cashierSales(CashShift $shift)
    {
        return Sale::where('employee_id', $shift->user_id)
            ->where('created_at', '>=', $shift->opened_at)
            ->where('created_at', '<=', $shift->closed_at ?? now());
    }
}
