<?php

namespace App\Http\Controllers;

use App\Models\JournalEntry;
use Illuminate\Http\Request;

class JournalEntryController extends Controller
{
    public function index()
    {
        $entries = JournalEntry::with('lines.account')->latest()->paginate(15);
        return view('journal_entries.index', compact('entries')); // تم التعديل هنا
    }

    public function create()
    {
        return view('journal-entries.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'entry_number' => 'required|string|unique:journal_entries,entry_number',
            'entry_date' => 'required|date',
            'description' => 'required|string',
        ]);

        $entry = JournalEntry::create($request->only(['organization_id', 'entry_number', 'entry_date', 'description', 'ref_type', 'ref_id']));

        if ($request->has('lines') && is_array($request->lines)) {
            $entry->lines()->createMany($request->lines);
        }

        return redirect()->route('journal-entries.index')->with('success', 'تم تسجيل القيد اليومي بنجاح.');
    }

    // تمت إضافة الدالة هنا لمنع ظهور خطأ Method does not exist عند استدعاء رابط التفاصيل القديم
    public function show(JournalEntry $journalEntry)
    {
        return redirect()->route('journal-entries.index');
    }

    public function destroy(JournalEntry $journalEntry)
    {
        $journalEntry->lines()->delete();
        $journalEntry->delete();
        return redirect()->route('journal-entries.index')->with('success', 'تم حذف القيد بنجاح.');
    }

    public function update(Request $request, JournalEntry $journalEntry)
    {
        $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'entry_number' => 'required|string|unique:journal_entries,entry_number,' . $journalEntry->id,
            'entry_date' => 'required|date',
            'description' => 'required|string',
        ]);

        $journalEntry->update($request->only(['organization_id', 'entry_number', 'entry_date', 'description', 'ref_type', 'ref_id']));

        return redirect()->route('journal-entries.index')->with('success', 'تم تحديث القيد اليومي بنجاح.');
    }
}
