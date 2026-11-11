<?php

namespace App\Http\Controllers;

use App\Models\JournalEntry;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JournalEntryController extends Controller
{
    public function index()
    {
        $entries = JournalEntry::with(['lines.account', 'organization'])->latest()->paginate(15);

        $organizations = \App\Models\Organization::orderBy('name')->get(['id', 'name']);
        $accountsList = \App\Models\Account::orderBy('account_code')->get(['id', 'name', 'account_code']);

        return view('journal_entries.index', compact('entries', 'organizations', 'accountsList'));
    }

    // نموذج الإنشاء موجود داخل صفحة القائمة نفسها
    public function create()
    {
        return redirect()->route('journal-entries.index');
    }

    public function store(Request $request)
    {
        $request->validate([
            'organization_id'    => 'required|exists:organizations,id,deleted_at,NULL',
            'entry_number'       => 'required|string|unique:journal_entries,entry_number',
            'entry_date'         => 'required|date',
            'description'        => 'required|string',
            'lines'              => 'required|array|min:2',
            'lines.*.account_id' => 'required|exists:accounts,id,deleted_at,NULL',
            'lines.*.debit'      => 'nullable|numeric|min:0',
            'lines.*.credit'     => 'nullable|numeric|min:0',
        ], [
            'lines.required' => 'يجب إدخال سطور القيد (سطرين على الأقل).',
            'lines.min'      => 'يجب إدخال سطرين على الأقل في القيد.',
        ]);

        try {
            AccountingService::post(
                (int) $request->organization_id,
                $request->description,
                $request->lines,
                $request->entry_number,
                $request->entry_date
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors($e->getMessage())->withInput();
        }

        return redirect()->route('journal-entries.index')->with('success', 'تم تسجيل القيد اليومي بنجاح.');
    }

    public function show(JournalEntry $journalEntry)
    {
        return redirect()->route('journal-entries.index');
    }

    public function edit(JournalEntry $journalEntry)
    {
        return redirect()->route('journal-entries.index');
    }

    public function update(Request $request, JournalEntry $journalEntry)
    {
        // يتم تعديل بيانات رأس القيد فقط، السطور تبقى كما هي حفاظاً على التوازن
        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id,deleted_at,NULL',
            'entry_number'    => 'required|string|unique:journal_entries,entry_number,' . $journalEntry->id,
            'entry_date'      => 'required|date',
            'description'     => 'required|string',
        ]);

        $journalEntry->update($validated);

        return redirect()->route('journal-entries.index')->with('success', 'تم تحديث القيد اليومي بنجاح.');
    }

    public function destroy(JournalEntry $journalEntry)
    {
        DB::transaction(function () use ($journalEntry) {
            $journalEntry->lines()->delete();
            $journalEntry->delete();
        });

        return redirect()->route('journal-entries.index')->with('success', 'تم حذف القيد بنجاح.');
    }
}
