<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index()
    {
        $accounts = Account::with('parent', 'children')->paginate(15);
        $organizations = \App\Models\Organization::orderBy('name')->get(['id', 'name']);
        $parentAccounts = Account::whereNull('parent_id')->orderBy('account_code')->get(['id', 'name', 'account_code']);

        return view('accounts.index', compact('accounts', 'organizations', 'parentAccounts'));
    }

    public function create()
    {
        // نموذج الإضافة موجود داخل صفحة القائمة
        return redirect()->route('accounts.index');
    }

    public function store(Request $request)
    {
        // إذا لم يتم إرسال المنظمة، نجعلها تأخذ أول منظمة متاحة تلقائياً
        $request->merge([
            'organization_id' => \App\Support\Tenancy::orgId() ?? $request->organization_id ?? \App\Models\Organization::first()?->id
        ]);

        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id,deleted_at,NULL',
            'parent_id' => 'nullable|exists:accounts,id,deleted_at,NULL',
            'name' => 'required|string|max:255',
            'account_code' => ['required', 'string', \Illuminate\Validation\Rule::unique('accounts', 'account_code')->where('organization_id', $request->organization_id)],
            'account_type' => 'required|string',
        ]);

        Account::create($validated);

        return redirect()->route('accounts.index')->with('success', 'تم إنشاء الحساب بنجاح.');
    }

    public function show(Account $account)
    {
        $account->load(['parent', 'children']);
        $lines = $account->journalEntryLines()->with('journalEntry')->latest()->paginate(20);
        $totalDebit = (float) $account->journalEntryLines()->sum('debit');
        $totalCredit = (float) $account->journalEntryLines()->sum('credit');

        return view('accounts.show', compact('account', 'lines', 'totalDebit', 'totalCredit'));
    }

    public function edit(Account $account)
    {
        // التعديل يتم من نفس صفحة القائمة
        return redirect()->route('accounts.index');
    }

    public function update(Request $request, Account $account)
    {
        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id,deleted_at,NULL',
            'parent_id' => 'nullable|exists:accounts,id,deleted_at,NULL|not_in:' . $account->id,
            'name' => 'required|string|max:255',
            'account_code' => ['required', 'string', \Illuminate\Validation\Rule::unique('accounts', 'account_code')->where('organization_id', $account->organization_id)->ignore($account->id)],
            'account_type' => 'required|string',
        ]);

        $account->update($validated);

        return redirect()->route('accounts.index')->with('success', 'تم تحديث الحساب بنجاح.');
    }

    public function destroy(Account $account)
    {
        if ($account->journalEntryLines()->exists() || $account->children()->exists()) {
            return redirect()->route('accounts.index')->with('error', 'لا يمكن حذف حساب عليه قيود أو له حسابات فرعية.');
        }

        $account->delete();
        return redirect()->route('accounts.index')->with('success', 'تم حذف الحساب بنجاح.');
    }
}
