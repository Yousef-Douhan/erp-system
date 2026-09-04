<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\CustomerAccount;
use App\Models\SupplierAccount;
use App\Models\CashRegister;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdvancedAccountingController extends Controller
{
    /**
     * 1. سند قبض (Receipt Voucher) - قبض نقدية من عميل أو إيراد
     */
    public function storeReceiptVoucher(Request $request)
    {
        $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'cash_register_id' => 'required|exists:cash_registers,id',
            'customer_id' => 'nullable|exists:customers,id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string',
        ]);

        try {
            DB::beginTransaction();

            // 1. إنشاء قيد يومي تلقائي (من مذكورين / إلى مذكورين)
            $entry = JournalEntry::create([
                'organization_id' => $request->organization_id,
                'entry_number' => 'RV-' . date('Ymd') . '-' . rand(1000, 9999),
                'entry_date' => now(),
                'description' => 'سند قبض: ' . $request->description,
                'ref_type' => 'receipt_voucher',
                'ref_id' => null,
            ]);

            // فرضا: الخزينة مدينة (زيادة النقدية)
            // نحتاج حساب الخزينة وحساب العميل من دليل الحسابات (Accounts)
            // للتسهيل، سنفترض ربطها بأكواد افتراضية أو تمريرها

            // تحديث رصيد الخزينة
            $register = CashRegister::findOrFail($request->cash_register_id);
            $register->increment('current_balance', $request->amount);

            // إذا كان مرتبط بعميل، نخفض مديونية العميل
            if ($request->customer_id) {
                $latestAccount = CustomerAccount::where('customer_id', $request->customer_id)->latest()->first();
                $lastBalance = $latestAccount ? $latestAccount->balance : 0;
                $newBalance = $lastBalance - $request->amount;

                CustomerAccount::create([
                    'customer_id' => $request->customer_id,
                    'transaction_type' => 'receipt_voucher',
                    'debit' => 0,
                    'credit' => $request->amount,
                    'balance' => $newBalance,
                ]);
            }

            DB::commit();
            return back()->with('success', 'تم إنشاء سند القبض وتسجيل القيود بنجاح.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'حدث خطأ أثناء حفظ سند القبض: ' . $e->getMessage());
        }
    }

    /**
     * 2. سند صرف (Payment Voucher) - دفع نقدية لمورد أو مصروف
     */
    public function storePaymentVoucher(Request $request)
    {
        $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'cash_register_id' => 'required|exists:cash_registers,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string',
        ]);

        try {
            DB::beginTransaction();

            // 1. إنشاء قيد يومي تلقائي
            $entry = JournalEntry::create([
                'organization_id' => $request->organization_id,
                'entry_number' => 'PV-' . date('Ymd') . '-' . rand(1000, 9999),
                'entry_date' => now(),
                'description' => 'سند صرف: ' . $request->description,
                'ref_type' => 'payment_voucher',
                'ref_id' => null,
            ]);

            // تحديث رصيد الخزينة (خصم المبلغ)
            $register = CashRegister::findOrFail($request->cash_register_id);
            if ($register->current_balance < $request->amount) {
                return back()->with('error', 'رصيد الخزينة الحالي لا يكفي لإتمام عملية الصرف!');
            }
            $register->decrement('current_balance', $request->amount);

            // إذا كان مرتبط بمورد، نخفض المستحق للمورد
            if ($request->supplier_id) {
                $latestAccount = SupplierAccount::where('supplier_id', $request->supplier_id)->latest()->first();
                $lastBalance = $latestAccount ? $latestAccount->balance : 0;
                $newBalance = $lastBalance - $request->amount;

                SupplierAccount::create([
                    'supplier_id' => $request->supplier_id,
                    'transaction_type' => 'payment_voucher',
                    'debit' => $request->amount,
                    'credit' => 0,
                    'balance' => $newBalance,
                ]);
            }

            DB::commit();
            return back()->with('success', 'تم إنشاء سند الصرف بنجاح وتحديث الخزينة.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'حدث خطأ: ' . $e->getMessage());
        }
    }

    /**
     * 3. تقرير قائمة الدخل (Income Statement / Profit & Loss)
     */
    public function incomeStatement()
    {
        // حساب إجمالي الإيرادات والمصروفات من قيود اليومية أو جدول الحسابات
        // استخراج الحسابات التي نوعها إيرادات ومصروفات
        $revenues = Account::where('account_type', 'revenue')->with('journalEntryLines')->get();
        $expenses = Account::where('account_type', 'expense')->with('journalEntryLines')->get();

        $totalRevenue = 0;
        foreach ($revenues as $rev) {
            $totalRevenue += $rev->journalEntryLines->sum('credit') - $rev->journalEntryLines->sum('debit');
        }

        $totalExpense = 0;
        foreach ($expenses as $exp) {
            $totalExpense += $exp->journalEntryLines->sum('debit') - $exp->journalEntryLines->sum('credit');
        }

        $netProfit = $totalRevenue - $totalExpense;

        return view('accounting.reports.income_statement', compact('revenues', 'expenses', 'totalRevenue', 'totalExpense', 'netProfit'));
    }

    /**
     * 4. تقرير المركز المالي (Balance Sheet)
     */
    public function balanceSheet()
    {
        $assets = Account::where('account_type', 'asset')->with('journalEntryLines')->get();
        $liabilities = Account::where('account_type', 'liability')->with('journalEntryLines')->get();
        $equity = Account::where('account_type', 'equity')->with('journalEntryLines')->get();

        $totalAssets = $assets->sum(fn($acc) => $acc->journalEntryLines->sum('debit') - $acc->journalEntryLines->sum('credit'));
        $totalLiabilities = $liabilities->sum(fn($acc) => $acc->journalEntryLines->sum('credit') - $acc->journalEntryLines->sum('debit'));
        $totalEquity = $equity->sum(fn($acc) => $acc->journalEntryLines->sum('credit') - $acc->journalEntryLines->sum('debit'));

        return view('accounting.reports.balance_sheet', compact('assets', 'liabilities', 'equity', 'totalAssets', 'totalLiabilities', 'totalEquity'));
    }
}
