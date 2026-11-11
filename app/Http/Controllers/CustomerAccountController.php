<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Services\CustomerLedger;
use Illuminate\Http\Request;

class CustomerAccountController extends Controller
{
    public function index(Request $request)
    {
        $customerAccounts = CustomerAccount::with('customer')->latest()->paginate(15);
        $customers = Customer::orderBy('name')->get(['id', 'name']);

        return view('customer_accounts.index', compact('customerAccounts', 'customers'));
    }

    /**
     * تسجيل حركة يدوية على حساب العميل.
     * تمر عبر CustomerLedger حتى يتحدث رصيد العميل ودفتر الديون معاً.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id'      => 'required|exists:customers,id,deleted_at,NULL',
            'transaction_type' => 'required|string|max:100',
            'debit'            => 'required|numeric|min:0',
            'credit'           => 'required|numeric|min:0',
        ]);

        $debit = (float) $validated['debit'];
        $credit = (float) $validated['credit'];

        if (($debit > 0) === ($credit > 0)) {
            return back()->withErrors('أدخل قيمة في المدين أو في الدائن فقط (وليس الاثنين أو لا شيء).')->withInput();
        }

        try {
            if ($debit > 0) {
                CustomerLedger::addDebt((int) $validated['customer_id'], $debit, 'حركة يدوية: ' . $validated['transaction_type'], $validated['transaction_type']);
            } else {
                CustomerLedger::recordPayment((int) $validated['customer_id'], $credit, 'حركة يدوية: ' . $validated['transaction_type'], $validated['transaction_type']);
            }
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors($e->getMessage())->withInput();
        }

        return back()->with('success', 'تم تسجيل المعاملة المالية للعميل بنجاح.');
    }

    public function show(CustomerAccount $customerAccount)
    {
        $customerAccount->load('customer');
        $statement = CustomerAccount::where('customer_id', $customerAccount->customer_id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(25);

        return view('customer_accounts.show', compact('customerAccount', 'statement'));
    }
}
