<?php

namespace App\Http\Controllers;

use App\Models\CashRegister;
use App\Models\Branch; // استدعاء مودل الفروع
use Illuminate\Http\Request;

class CashRegisterController extends Controller
{
    public function index()
    {
        $registers = CashRegister::with('branch')->paginate(15);
        $branches = Branch::all(); // جلب جميع الفروع لعرضها في القائمة المنسدلة
        return view('cash_registers.index', compact('registers', 'branches'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id,deleted_at,NULL',
            'name' => 'required|string|max:255',
            'opening_balance' => 'required|numeric|min:0',
            'status' => 'required|string',
        ]);

        // تعيين الرصيد الحالي ليساوي الرصيد الافتتاحي عند الإنشاء لأول مرة
        $validated['current_balance'] = $request->opening_balance;

        CashRegister::create($validated);

        return redirect()->route('cash-registers.index')->with('success', 'تم إنشاء الخزينة بنجاح.');
    }

    public function update(Request $request, CashRegister $cashRegister)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id,deleted_at,NULL',
            'name' => 'required|string|max:255',
            'status' => 'required|string',
        ]);

        // نقوم بتحديث البيانات الأساسية فقط دون المساس بالأرصدة لضمان استقرار الحسابات
        $cashRegister->update($validated);

        return redirect()->route('cash-registers.index')->with('success', 'تم تحديث بيانات الخزينة بنجاح.');
    }

    public function destroy(CashRegister $cashRegister)
    {
        $cashRegister->delete();
        return redirect()->route('cash-registers.index')->with('success', 'تم حذف الخزينة بنجاح.');
    }
}
