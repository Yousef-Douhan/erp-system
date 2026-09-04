<?php

namespace App\Http\Controllers;

use App\Models\SupplierAccount;
use App\Models\Supplier; // استدعاء مودل الموردين
use Illuminate\Http\Request;

class SupplierAccountController extends Controller
{
    public function index()
    {
        $supplierAccounts = SupplierAccount::with('supplier')->latest()->paginate(15);
        $suppliers = Supplier::all(); // جلب قائمة الموردين لعرضها في القائمة المنسدلة
        return view('supplier_accounts.index', compact('supplierAccounts', 'suppliers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'transaction_type' => 'required|string',
            'debit' => 'required|numeric|min:0',
            'credit' => 'required|numeric|min:0',
        ]);

        // حساب الرصيد التراكمي للمورد تلقائياً بناءً على آخر حركة له
        $lastAccount = SupplierAccount::where('supplier_id', $request->supplier_id)->latest()->first();
        $previousBalance = $lastAccount ? $lastAccount->balance : 0;

        // المعادلة للموردين (عادة الرصيد السابق + المدين - الدائن أو العكس حسب طبيعة النظام، هنا اعتمدنا نفس المنطق)
        $validated['balance'] = $previousBalance + $request->debit - $request->credit;

        SupplierAccount::create($validated);

        return back()->with('success', 'تم تسجيل المعاملة المالية للمورد بنجاح.');
    }

    public function show(SupplierAccount $supplierAccount)
    {
        return view('supplier_accounts.show', compact('supplierAccount'));
    }
}
