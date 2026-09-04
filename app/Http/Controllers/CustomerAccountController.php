<?php

namespace App\Http\Controllers;

use App\Models\CustomerAccount;
use App\Models\Customer; // تأكد من استدعاء مودل العملاء
use Illuminate\Http\Request;

class CustomerAccountController extends Controller
{
    public function index(Request $request)
    {
        $customerAccounts = CustomerAccount::with('customer')->latest()->paginate(15);
        $customers = Customer::all(); // جلب قائمة العملاء لعرضها في القائمة المنسدلة
        return view('customer_accounts.index', compact('customerAccounts', 'customers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'transaction_type' => 'required|string',
            'debit' => 'required|numeric|min:0',
            'credit' => 'required|numeric|min:0',
        ]);

        // حساب الرصيد التراكمي للعميل تلقائياً
        $lastAccount = CustomerAccount::where('customer_id', $request->customer_id)->latest()->first();
        $previousBalance = $lastAccount ? $lastAccount->balance : 0;

        $validated['balance'] = $previousBalance + $request->debit - $request->credit;

        CustomerAccount::create($validated);

        return back()->with('success', 'تم تسجيل المعاملة المالية للعميل بنجاح.');
    }

    public function show(CustomerAccount $customerAccount)
    {
        return view('customer_accounts.show', compact('customerAccount'));
    }
}
