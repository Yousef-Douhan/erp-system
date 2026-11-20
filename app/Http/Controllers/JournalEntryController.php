<?php

namespace App\Http\Controllers;

use App\Models\JournalEntry;
use App\Services\AccountingService;
use Illuminate\Http\Request;

class JournalEntryController extends Controller
{
    /** أنواع القيود التي ينشئها النظام تلقائياً (لا تُعدَّل ولا تُحذف — تُصحَّح بقيد عكسي) */
    public const SOURCES = [
        'sale' => 'فاتورة بيع', 'sale_payment' => 'دفعة على فاتورة', 'sale_return' => 'مرتجع مبيعات',
        'customer_payment' => 'تحصيل دين', 'purchase' => 'فاتورة شراء', 'purchase_payment' => 'دفعة لمورد',
        'purchase_return' => 'مرتجع للمورد', 'expense' => 'مصروف', 'receipt_voucher' => 'سند قبض',
        'payment_voucher' => 'سند صرف', 'cash_register' => 'خزينة', 'cash_shift' => 'وردية',
        'supplier_opening' => 'رصيد مورد افتتاحي', 'reversal' => 'قيد عكسي',
    ];

    public function index()
    {
        $entries = JournalEntry::with(['lines.account:id,name,account_code', 'organization:id,name'])->latest('id')->paginate(15);

        // القيود التي عُكست (لإخفاء زر العكس عنها)
        $reversed = JournalEntry::where('ref_type', 'reversal')
            ->whereIn('ref_id', $entries->pluck('id'))
            ->pluck('entry_number', 'ref_id');

        $organizations = \App\Models\Organization::orderBy('name')->get(['id', 'name']);
        $accountsList = \App\Models\Account::orderBy('account_code')->get(['id', 'name', 'account_code']);
        $sources = self::SOURCES;

        return view('journal_entries.index', compact('entries', 'organizations', 'accountsList', 'reversed', 'sources'));
    }

    /** قيد يدوي جديد (يجب أن يتوازن) */
    public function store(Request $request)
    {
        $request->validate([
            'organization_id'    => 'required|exists:organizations,id,deleted_at,NULL',
            'entry_number'       => 'nullable|string|max:50|unique:journal_entries,entry_number',
            'entry_date'         => 'required|date',
            'description'        => 'required|string|max:1000',
            'lines'              => 'required|array|min:2',
            'lines.*.account_id' => 'required|exists:accounts,id,deleted_at,NULL',
            'lines.*.debit'      => 'nullable|numeric|min:0',
            'lines.*.credit'     => 'nullable|numeric|min:0',
        ], [
            'lines.required' => 'يجب إدخال سطور القيد (سطرين على الأقل).',
            'lines.min'      => 'يجب إدخال سطرين على الأقل في القيد.',
        ]);

        // الحسابات يجب أن تكون من دليل نفس الصيدلية
        $orgId = \App\Support\Tenancy::active() ? (int) \App\Support\Tenancy::orgId() : (int) $request->organization_id;
        $accountIds = collect($request->lines)->pluck('account_id')->map(fn ($v) => (int) $v)->unique();
        if (\App\Models\Account::whereIn('id', $accountIds)->count() !== $accountIds->count()) {
            return back()->withErrors('أحد الحسابات المختارة لا يتبع صيدليتك.')->withInput();
        }

        try {
            AccountingService::post(
                $orgId,
                $request->description,
                $request->lines,
                $request->entry_number ?: AccountingService::nextEntryNumber('JE'),
                $request->entry_date
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors($e->getMessage())->withInput();
        }

        return redirect()->route('journal-entries.index')->with('success', 'تم تسجيل القيد اليومي بنجاح.');
    }

    /**
     * تعديل البيان والتاريخ فقط، وللقيود اليدوية فقط. السطور والمبالغ لا تتغير:
     * التصحيح يكون بقيد عكسي ثم قيد صحيح.
     */
    public function update(Request $request, JournalEntry $journalEntry)
    {
        if ($journalEntry->ref_type) {
            return back()->with('error', 'هذا قيد أنشأه النظام تلقائياً (' . (self::SOURCES[$journalEntry->ref_type] ?? $journalEntry->ref_type) . ') ولا يُعدَّل. صحّح المستند نفسه أو اعمل قيداً عكسياً.');
        }

        $validated = $request->validate([
            'entry_date'  => 'required|date',
            'description' => 'required|string|max:1000',
        ]);

        $journalEntry->update($validated);

        return redirect()->route('journal-entries.index')->with('success', 'تم تحديث بيان القيد.');
    }

    /**
     * قيد عكسي: نفس السطور بعكس المدين والدائن، فيلغي أثر القيد الأصلي مع بقاء الاثنين في السجل.
     */
    public function reverse(JournalEntry $journalEntry)
    {
        if ($journalEntry->ref_type === 'reversal') {
            return back()->with('error', 'هذا قيد عكسي بالفعل.');
        }
        if (JournalEntry::where('ref_type', 'reversal')->where('ref_id', $journalEntry->id)->exists()) {
            return back()->with('error', 'تم عكس هذا القيد من قبل.');
        }

        $lines = $journalEntry->lines->map(fn ($l) => [
            'account_id' => $l->account_id,
            'debit'      => (float) $l->credit,
            'credit'     => (float) $l->debit,
        ])->all();

        $entry = AccountingService::post(
            (int) $journalEntry->organization_id,
            'عكس القيد ' . $journalEntry->entry_number . ': ' . $journalEntry->description,
            $lines,
            AccountingService::nextEntryNumber('RV'),
            null,
            'reversal',
            $journalEntry->id
        );

        return redirect()->route('journal-entries.index')->with('success', 'تم تسجيل القيد العكسي ' . $entry->entry_number . '.');
    }
}
