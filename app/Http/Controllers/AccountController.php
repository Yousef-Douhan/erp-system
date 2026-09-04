<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index()
    {
        $accounts = Account::with('parent', 'children')->paginate(15);
        return view('accounts.index', compact('accounts'));
    }

    public function create()
    {
        $parentAccounts = Account::whereNull('parent_id')->get();
        return view('accounts.create', compact('parentAccounts'));
    }

    public function store(Request $request)
    {
        // إذا لم يتم إرسال المنظمة، نجعلها تأخذ أول منظمة متاحة تلقائياً
        $request->merge([
            'organization_id' => $request->organization_id ?? \App\Models\Organization::first()?->id
        ]);

        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'parent_id' => 'nullable|exists:accounts,id',
            'name' => 'required|string|max:255',
            'account_code' => 'required|string|unique:accounts,account_code',
            'account_type' => 'required|string',
        ]);

        Account::create($validated);

        return redirect()->route('accounts.index')->with('success', 'تم إنشاء الحساب بنجاح.');
    }

    public function show(Account $account)
    {
        return view('accounts.show', compact('account'));
    }

    public function edit(Account $account)
    {
        $parentAccounts = Account::where('id', '!=', $account->id)->whereNull('parent_id')->get();
        return view('accounts.edit', compact('account', 'parentAccounts'));
    }

    public function update(Request $request, Account $account)
    {
        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'parent_id' => 'nullable|exists:accounts,id',
            'name' => 'required|string|max:255',
            'account_code' => 'required|string|unique:accounts,account_code,' . $account->id,
            'account_type' => 'required|string',
        ]);

        $account->update($validated);

        return redirect()->route('accounts.index')->with('success', 'تم تحديث الحساب بنجاح.');
    }

    public function destroy(Account $account)
    {
        $account->delete();
        return redirect()->route('accounts.index')->with('success', 'تم حذف الحساب بنجاح.');
    }
}
