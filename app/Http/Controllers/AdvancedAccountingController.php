<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\SupplierAccount;
use App\Services\AccountingService;
use App\Services\CustomerLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdvancedAccountingController extends Controller
{
    /**
     * 1. سند قبض (Receipt Voucher) - قبض نقدية من عميل أو إيراد آخر
     * القيد: من حـ/ الصندوق  إلى حـ/ ذمم العملاء (أو إيرادات أخرى)
     */
    public function storeReceiptVoucher(Request $request)
    {
        $request->validate([
            'organization_id'  => 'required|exists:organizations,id,deleted_at,NULL',
            'cash_register_id' => 'required|exists:cash_registers,id,deleted_at,NULL',
            'customer_id'      => 'nullable|exists:customers,id,deleted_at,NULL',
            'amount'           => 'required|numeric|min:0.01',
            'description'      => 'required|string',
        ]);

        try {
            DB::transaction(function () use ($request) {
                $orgId = \App\Support\Tenancy::orgId() ?? (int) $request->organization_id;
                $amount = (float) $request->amount;

                $cash = AccountingService::systemAccount(AccountingService::CASH, $orgId);
                $creditAccount = $request->customer_id
                    ? AccountingService::systemAccount(AccountingService::RECEIVABLES, $orgId)
                    : AccountingService::systemAccount(AccountingService::OTHER_INCOME, $orgId);

                AccountingService::post($orgId, 'سند قبض: ' . $request->description, [
                    ['account_id' => $cash->id, 'debit' => $amount, 'credit' => 0],
                    ['account_id' => $creditAccount->id, 'debit' => 0, 'credit' => $amount],
                ], AccountingService::nextEntryNumber('RV'), null, 'receipt_voucher');

                \App\Services\CashBook::in((int) $request->cash_register_id, $amount, 'receipt_voucher', $request->description);

                if ($request->customer_id) {
                    CustomerLedger::recordPayment(
                        (int) $request->customer_id,
                        $amount,
                        'سند قبض: ' . $request->description,
                        'receipt_voucher'
                    );
                }
            });
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'حدث خطأ أثناء حفظ سند القبض، يرجى المحاولة مرة أخرى.')->withInput();
        }

        return back()->with('success', 'تم إنشاء سند القبض وتسجيل القيد بنجاح.');
    }

    /**
     * 2. سند صرف (Payment Voucher) - صرف نقدية لمورد أو مصروف آخر
     * القيد: من حـ/ ذمم الموردين (أو مصروفات أخرى)  إلى حـ/ الصندوق
     */
    public function storePaymentVoucher(Request $request)
    {
        $request->validate([
            'organization_id'  => 'required|exists:organizations,id,deleted_at,NULL',
            'cash_register_id' => 'required|exists:cash_registers,id,deleted_at,NULL',
            'supplier_id'      => 'nullable|exists:suppliers,id,deleted_at,NULL',
            'amount'           => 'required|numeric|min:0.01',
            'description'      => 'required|string',
        ]);

        try {
            DB::transaction(function () use ($request) {
                $orgId = \App\Support\Tenancy::orgId() ?? (int) $request->organization_id;
                $amount = (float) $request->amount;

                $register = CashRegister::whereKey($request->cash_register_id)->lockForUpdate()->firstOrFail();
                if ($register->current_balance < $amount) {
                    throw new \InvalidArgumentException('رصيد الخزينة الحالي لا يكفي لإتمام عملية الصرف!');
                }

                $cash = AccountingService::systemAccount(AccountingService::CASH, $orgId);
                $debitAccount = $request->supplier_id
                    ? AccountingService::systemAccount(AccountingService::PAYABLES, $orgId)
                    : AccountingService::systemAccount(AccountingService::OTHER_EXPENSES, $orgId);

                AccountingService::post($orgId, 'سند صرف: ' . $request->description, [
                    ['account_id' => $debitAccount->id, 'debit' => $amount, 'credit' => 0],
                    ['account_id' => $cash->id, 'debit' => 0, 'credit' => $amount],
                ], AccountingService::nextEntryNumber('PV'), null, 'payment_voucher');

                \App\Services\CashBook::out($register, $amount, 'payment_voucher', $request->description);

                if ($request->supplier_id) {
                    $supplier = Supplier::whereKey($request->supplier_id)->lockForUpdate()->firstOrFail();
                    $supplier->balance = round((float) $supplier->balance - $amount, 2);
                    $supplier->save();

                    SupplierAccount::create([
                        'supplier_id'      => $supplier->id,
                        'transaction_type' => 'payment_voucher',
                        'debit'            => $amount,
                        'credit'           => 0,
                        'balance'          => $supplier->balance,
                    ]);
                }
            });
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'حدث خطأ أثناء حفظ سند الصرف، يرجى المحاولة مرة أخرى.')->withInput();
        }

        return back()->with('success', 'تم إنشاء سند الصرف بنجاح وتحديث الخزينة.');
    }

    /**
     * 3. قائمة الدخل (Income Statement)
     */
    public function incomeStatement(Request $request)
    {
        [$from, $to] = $this->period($request);

        $revenues = $this->accountsWithTotals('revenue', $from, $to);
        $expenses = $this->accountsWithTotals('expense', $from, $to);

        $totalRevenue = $revenues->sum(fn ($acc) => $acc->total_credit - $acc->total_debit);
        $totalExpense = $expenses->sum(fn ($acc) => $acc->total_debit - $acc->total_credit);
        $netProfit = $totalRevenue - $totalExpense;

        return view('accounting.reports.income_statement', compact('revenues', 'expenses', 'totalRevenue', 'totalExpense', 'netProfit', 'from', 'to'));
    }

    /**
     * 4. الميزانية العمومية (Balance Sheet)
     */
    public function balanceSheet(Request $request)
    {
        [, $to] = $this->period($request);

        $assets = $this->accountsWithTotals('asset', null, $to);
        $liabilities = $this->accountsWithTotals('liability', null, $to);
        $equity = $this->accountsWithTotals('equity', null, $to);

        $totalAssets = $assets->sum(fn ($acc) => $acc->total_debit - $acc->total_credit);
        $totalLiabilities = $liabilities->sum(fn ($acc) => $acc->total_credit - $acc->total_debit);
        $totalEquity = $equity->sum(fn ($acc) => $acc->total_credit - $acc->total_debit);

        // صافي الربح غير المرحّل يظهر ضمن حقوق الملكية حتى تتوازن الميزانية
        $revenue = $this->accountsWithTotals('revenue', null, $to)->sum(fn ($a) => $a->total_credit - $a->total_debit);
        $expense = $this->accountsWithTotals('expense', null, $to)->sum(fn ($a) => $a->total_debit - $a->total_credit);
        $retainedEarnings = $revenue - $expense;

        return view('accounting.reports.balance_sheet', compact(
            'assets', 'liabilities', 'equity', 'totalAssets', 'totalLiabilities', 'totalEquity', 'retainedEarnings', 'to'
        ));
    }

    private function period(Request $request): array
    {
        $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);

        return [$request->input('from'), $request->input('to')];
    }

    /**
     * جلب الحسابات مع مجموع المدين والدائن من قاعدة البيانات مباشرة (بدون تحميل كل السطور)
     */
    private function accountsWithTotals(string $type, ?string $from, ?string $to)
    {
        $filter = function ($q) use ($from, $to) {
            $q->whereHas('journalEntry', function ($e) use ($from, $to) {
                if ($from) {
                    $e->whereDate('entry_date', '>=', $from);
                }
                if ($to) {
                    $e->whereDate('entry_date', '<=', $to);
                }
            });
        };

        return Account::where('account_type', $type)
            ->withSum(['journalEntryLines as total_debit' => $filter], 'debit')
            ->withSum(['journalEntryLines as total_credit' => $filter], 'credit')
            ->orderBy('account_code')
            ->get()
            ->each(function ($acc) {
                $acc->total_debit = (float) $acc->total_debit;
                $acc->total_credit = (float) $acc->total_credit;
            });
    }
}
