<?php

namespace App\Http\Controllers;

use App\Models\JournalEntryLine;
use Illuminate\Http\Request;

class JournalEntryLineController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'journal_entry_id' => 'required|exists:journal_entries,id,deleted_at,NULL',
            'account_id' => 'required|exists:accounts,id,deleted_at,NULL',
            'debit' => 'required|numeric|min:0',
            'credit' => 'required|numeric|min:0',
        ]);

        JournalEntryLine::create($validated);

        return back()->with('success', 'تمت إضافة سطر القيد بنجاح.');
    }

    public function destroy(JournalEntryLine $journalEntryLine)
    {
        $journalEntryLine->delete();
        return back()->with('success', 'تم حذف سطر القيد.');
    }
}
