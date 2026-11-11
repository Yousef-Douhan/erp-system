@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex justify-between items-start">
        <div>
            <h3 class="text-base font-black text-slate-800">{{ $account->name }}</h3>
            <p class="text-xs text-slate-500 mt-1">
                <span class="font-mono font-bold text-indigo-600">{{ $account->account_code }}</span>
                · {{ $account->account_type }}
                @if($account->parent) · تابع لـ: {{ $account->parent->name }} @endif
            </p>
        </div>
        <a href="{{ route('accounts.index') }}" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-xl text-xs font-bold hover:bg-slate-200">رجوع</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-slate-200">
            <div class="text-xs text-slate-500 font-bold">إجمالي المدين</div>
            <div class="text-lg font-black text-slate-800 mt-1">{{ number_format($totalDebit, 2) }}</div>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-slate-200">
            <div class="text-xs text-slate-500 font-bold">إجمالي الدائن</div>
            <div class="text-lg font-black text-slate-800 mt-1">{{ number_format($totalCredit, 2) }}</div>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-slate-200">
            <div class="text-xs text-slate-500 font-bold">الرصيد (مدين − دائن)</div>
            <div class="text-lg font-black text-indigo-600 mt-1">{{ number_format($totalDebit - $totalCredit, 2) }}</div>
        </div>
    </div>

    @if($account->children->isNotEmpty())
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <h4 class="text-sm font-black text-slate-800 mb-3">الحسابات الفرعية</h4>
            <div class="flex flex-wrap gap-2">
                @foreach($account->children as $child)
                    <a href="{{ route('accounts.show', $child) }}" class="px-3 py-1.5 bg-slate-100 rounded-xl text-xs font-bold text-slate-700 hover:bg-indigo-50">{{ $child->account_code }} - {{ $child->name }}</a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-4 border-b border-slate-100"><h4 class="text-sm font-black text-slate-800">حركات الحساب</h4></div>
        <table class="w-full text-right text-xs">
            <thead>
                <tr class="bg-slate-50 text-slate-500 border-b border-slate-200">
                    <th class="p-3 font-bold">التاريخ</th>
                    <th class="p-3 font-bold">رقم القيد</th>
                    <th class="p-3 font-bold">البيان</th>
                    <th class="p-3 font-bold">مدين</th>
                    <th class="p-3 font-bold">دائن</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($lines as $line)
                    <tr>
                        <td class="p-3 text-slate-600">{{ $line->journalEntry->entry_date ?? '-' }}</td>
                        <td class="p-3 font-mono text-indigo-600">{{ $line->journalEntry->entry_number ?? '-' }}</td>
                        <td class="p-3 text-slate-800">{{ $line->journalEntry->description ?? '' }}</td>
                        <td class="p-3 font-bold">{{ number_format($line->debit, 2) }}</td>
                        <td class="p-3 font-bold">{{ number_format($line->credit, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-6 text-center text-slate-400">لا توجد حركات على هذا الحساب.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4">{{ $lines->links() }}</div>
    </div>
</div>
@endsection
