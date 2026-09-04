<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\CashRegister;
use App\Models\Branch;
use Illuminate\Http\Request;

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
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'cash_register_id' => 'required|exists:cash_registers,id',
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'description' => 'nullable|string',
        ]);

        $expense = Expense::create($validated);

        // خصم المبلغ من الخزينة النقدية تلقائياً
        if ($expense->cash_register_id) {
            $register = CashRegister::find($expense->cash_register_id);
            if ($register) {
                $register->decrement('current_balance', $expense->amount);
            }
        }

        return redirect()->route('expenses.index')->with('success', 'تم تسجيل المصروف وخصمه من الخزينة بنجاح.');
    }

    public function update(Request $request, Expense $expense)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'cash_register_id' => 'required|exists:cash_registers,id',
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'description' => 'nullable|string',
        ]);

        // إعادة المبلغ القديم للخزينة القديمة مؤقتاً لتعديل الحسابات بدقة
        if ($expense->cash_register_id) {
            $oldRegister = CashRegister::find($expense->cash_register_id);
            if ($oldRegister) {
                $oldRegister->increment('current_balance', $expense->amount);
            }
        }

        // تحديث المصروف
        $expense->update($validated);

        // خصم المبلغ الجديد من الخزينة الجديدة
        if ($expense->cash_register_id) {
            $newRegister = CashRegister::find($expense->cash_register_id);
            if ($newRegister) {
                $newRegister->decrement('current_balance', $expense->amount);
            }
        }

        return redirect()->route('expenses.index')->with('success', 'تم تحديث سند المصروف بنجاح.');
    }

    public function destroy(Expense $expense)
    {
        // إعادة المبلغ للخزينة عند حذف المصروف
        if ($expense->cash_register_id) {
            $register = CashRegister::find($expense->cash_register_id);
            if ($register) {
                $register->increment('current_balance', $expense->amount);
            }
        }

        $expense->delete();
        return redirect()->route('expenses.index')->with('success', 'تم حذف سند المصروف.');
    }
}
