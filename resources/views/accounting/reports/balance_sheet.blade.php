@extends('layouts.app')

@section('content')
@php
    $sections = [
        ['title' => 'الأصول', 'items' => $assets, 'total' => $totalAssets, 'head' => 'bg-indigo-50 text-indigo-700', 'debitNature' => true],
        ['title' => 'الالتزامات', 'items' => $liabilities, 'total' => $totalLiabilities, 'head' => 'bg-amber-50 text-amber-700', 'debitNature' => false],
        ['title' => 'حقوق الملكية', 'items' => $equity, 'total' => $totalEquity, 'head' => 'bg-emerald-50 text-emerald-700', 'debitNature' => false],
    ];
    $totalLiabilitiesAndEquity = $totalLiabilities + $totalEquity + $retainedEarnings;
    $balanced = abs($totalAssets - $totalLiabilitiesAndEquity) < 0.01;
@endphp
<div class="max-w-5xl mx-auto space-y-6">
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex flex-col md:flex-row md:items-end md:justify-between gap-4">
        <div>
            <h3 class="text-base font-black text-slate-800">الميزانية العمومية (Balance Sheet)</h3>
            <p class="text-xs text-slate-400 mt-1">كما في: {{ $to ?: now()->toDateString() }}</p>
        </div>
        <form method="GET" action="{{ route('reports.balance-sheet') }}" class="flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-[11px] font-bold text-slate-500 mb-1">حتى تاريخ</label>
                <input type="date" name="to" value="{{ $to }}" class="px-3 py-2 rounded-xl border border-slate-200 text-xs">
            </div>
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-xs font-bold hover:bg-indigo-700">عرض</button>
            <button type="button" onclick="window.print()" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-xl text-xs font-bold hover:bg-slate-200">طباعة</button>
        </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        @foreach($sections as $section)
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden h-fit">
                <div class="p-4 border-b border-slate-100 {{ $section['head'] }}">
                    <h4 class="text-sm font-black">{{ $section['title'] }}</h4>
                </div>
                <table class="w-full text-right text-xs">
                    <tbody class="divide-y divide-slate-100">
                        @forelse($section['items'] as $acc)
                            @php $value = $section['debitNature'] ? $acc->total_debit - $acc->total_credit : $acc->total_credit - $acc->total_debit; @endphp
                            <tr>
                                <td class="p-3 text-slate-700"><span class="font-mono text-slate-400">{{ $acc->account_code }}</span> {{ $acc->name }}</td>
                                <td class="p-3 font-bold text-slate-800 text-left">{{ number_format($value, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="p-4 text-center text-slate-400">لا توجد حسابات.</td></tr>
                        @endforelse
                        @if($section['title'] === 'حقوق الملكية')
                            <tr>
                                <td class="p-3 text-slate-700">أرباح (خسائر) الفترة غير المرحّلة</td>
                                <td class="p-3 font-bold text-slate-800 text-left">{{ number_format($retainedEarnings, 2) }}</td>
                            </tr>
                        @endif
                    </tbody>
                    <tfoot>
                        <tr class="bg-slate-50 font-black">
                            <td class="p-3">الإجمالي</td>
                            <td class="p-3 text-left">{{ number_format($section['total'] + ($section['title'] === 'حقوق الملكية' ? $retainedEarnings : 0), 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endforeach
    </div>

    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
        <div class="flex justify-between"><span class="font-bold text-slate-600">إجمالي الأصول</span><span class="font-black">{{ number_format($totalAssets, 2) }}</span></div>
        <div class="flex justify-between"><span class="font-bold text-slate-600">الالتزامات + حقوق الملكية</span><span class="font-black">{{ number_format($totalLiabilitiesAndEquity, 2) }}</span></div>
        <div class="text-left font-black {{ $balanced ? 'text-emerald-600' : 'text-rose-600' }}">{{ $balanced ? 'الميزانية متوازنة ✓' : 'الميزانية غير متوازنة' }}</div>
    </div>
</div>
@endsection
