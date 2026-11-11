<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpenseController extends Controller
{
    public function index()
    {
        $expenses = Expense::with('branch', 'cashRegister')->latest()->paginate(15);
        $registers = CashRegister::where('status', 'active')->get(); // جلب الخزائن النشطة
        $branches = Branch::all(); // جلب الفروع

        return view('expenses.index', compact('expenses', 'registers', 'branches'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateExpense($request);

        DB::transaction(function () use ($validated) {
            $this->withdraw($validated['cash_register_id'], (float) $validated['amount']);
            $expense = Expense::create($validated);
            $this->postEntry($expense);
        });

        return redirect()->route('expenses.index')->with('success', 'تم تسجيل المصروف وخصمه من الخزينة بنجاح.');
    }

    public function update(Request $request, Expense $expense)
    {
        $validated = $this->validateExpense($request);

        DB::transaction(function () use ($expense, $validated) {
            // إرجاع المبلغ القديم للخزينة القديمة ثم خصم الجديد
            if ($expense->cash_register_id) {
                CashRegister::whereKey($expense->cash_register_id)->increment('current_balance', $expense->amount);
            }

            $this->withdraw($validated['cash_register_id'], (float) $validated['amount']);

            $expense->update($validated);

            $this->deleteEntry($expense);
            $this->postEntry($expense);
        });

        return redirect()->route('expenses.index')->with('success', 'تم تحديث سند المصروف بنجاح.');
    }

    public function destroy(Expense $expense)
    {
        DB::transaction(function () use ($expense) {
            if ($expense->cash_register_id) {
                CashRegister::whereKey($expense->cash_register_id)->increment('current_balance', $expense->amount);
            }

            $this->deleteEntry($expense);
            $expense->delete();
        });

        return redirect()->route('expenses.index')->with('success', 'تم حذف سند المصروف وإرجاع المبلغ للخزينة.');
    }

    private function validateExpense(Request $request): array
    {
        return $request->validate([
            'branch_id'        => 'required|exists:branches,id,deleted_at,NULL',
            'cash_register_id' => 'required|exists:cash_registers,id,deleted_at,NULL',
            'amount'           => 'required|numeric|min:0.01',
            'expense_date'     => 'required|date',
            'description'      => 'nullable|string',
        ]);
    }

    /**
     * خصم المبلغ من الخزينة مع منع الرصيد السالب
     */
    private function withdraw(int $registerId, float $amount): void
    {
        $register = CashRegister::whereKey($registerId)->lockForUpdate()->firstOrFail();

        if ((float) $register->current_balance < $amount) {
            throw ValidationException::withMessages([
                'amount' => 'رصيد الخزينة (' . number_format($register->current_balance, 2) . ') لا يكفي لهذا المصروف.',
            ]);
        }

        $register->decrement('current_balance', $amount);
    }

    /**
     * القيد: من حـ/ المصروفات  إلى حـ/ الصندوق
     */
    private function postEntry(Expense $expense): void
    {
        $orgId = Branch::whereKey($expense->branch_id)->value('organization_id');

        AccountingService::post($orgId, 'مصروف: ' . ($expense->description ?: 'بدون وصف'), [
            ['account_id' => AccountingService::systemAccount(AccountingService::EXPENSES, $orgId)->id, 'debit' => (float) $expense->amount, 'credit' => 0],
            ['account_id' => AccountingService::systemAccount(AccountingService::CASH, $orgId)->id, 'debit' => 0, 'credit' => (float) $expense->amount],
        ], AccountingService::nextEntryNumber('EX'), $expense->expense_date, 'expense', $expense->id);
    }

    private function deleteEntry(Expense $expense): void
    {
        JournalEntry::where('ref_type', 'expense')->where('ref_id', $expense->id)->get()->each(function ($entry) {
            $entry->lines()->delete();
            $entry->delete();
        });
    }
}
