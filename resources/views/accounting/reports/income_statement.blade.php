@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto">@include('partials.subnav', ['set' => 'accounts'])</div>
<div class="max-w-5xl mx-auto space-y-6">
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex flex-col md:flex-row md:items-end md:justify-between gap-4">
        <div>
            <h3 class="text-base font-black text-slate-800">قائمة الدخل (Income Statement)</h3>
            <p class="text-xs text-slate-400 mt-1">
                @if($from || $to)
                    الفترة: {{ $from ?: 'البداية' }} — {{ $to ?: 'اليوم' }}
                @else
                    كل الفترات
                @endif
            </p>
        </div>
        <form method="GET" action="{{ route('reports.income-statement') }}" class="flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-[11px] font-bold text-slate-500 mb-1">من</label>
                <input type="date" name="from" value="{{ $from }}" class="px-3 py-2 rounded-xl border border-slate-200 text-xs">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-500 mb-1">إلى</label>
                <input type="date" name="to" value="{{ $to }}" class="px-3 py-2 rounded-xl border border-slate-200 text-xs">
            </div>
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-xs font-bold hover:bg-indigo-700">عرض</button>
            <button type="button" onclick="window.print()" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-xl text-xs font-bold hover:bg-slate-200">طباعة</button>
        </form>
    </div>

    @if ($errors->any())
        <div class="p-3 bg-rose-50 text-rose-600 rounded-xl text-xs">{{ $errors->first() }}</div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-4 border-b border-slate-100 bg-emerald-50">
                <h4 class="text-sm font-black text-emerald-700">الإيرادات</h4>
            </div>
            <table class="w-full text-right text-xs">
                <tbody class="divide-y divide-slate-100">
                    @forelse($revenues as $acc)
                        <tr>
                            <td class="p-3 text-slate-700"><span class="font-mono text-slate-400">{{ $acc->account_code }}</span> {{ $acc->name }}</td>
                            <td class="p-3 font-bold text-slate-800 text-left">{{ number_format($acc->total_credit - $acc->total_debit, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="p-4 text-center text-slate-400">لا توجد حسابات إيرادات.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="bg-slate-50 font-black">
                        <td class="p-3">إجمالي الإيرادات</td>
                        <td class="p-3 text-left text-emerald-700">{{ number_format($totalRevenue, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-4 border-b border-slate-100 bg-rose-50">
                <h4 class="text-sm font-black text-rose-700">المصروفات</h4>
            </div>
            <table class="w-full text-right text-xs">
                <tbody class="divide-y divide-slate-100">
                    @forelse($expenses as $acc)
                        <tr>
                            <td class="p-3 text-slate-700"><span class="font-mono text-slate-400">{{ $acc->account_code }}</span> {{ $acc->name }}</td>
                            <td class="p-3 font-bold text-slate-800 text-left">{{ number_format($acc->total_debit - $acc->total_credit, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="p-4 text-center text-slate-400">لا توجد حسابات مصروفات.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="bg-slate-50 font-black">
                        <td class="p-3">إجمالي المصروفات</td>
                        <td class="p-3 text-left text-rose-700">{{ number_format($totalExpense, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex justify-between items-center">
        <span class="text-sm font-black text-slate-800">{{ $netProfit >= 0 ? 'صافي الربح' : 'صافي الخسارة' }}</span>
        <span class="text-xl font-black {{ $netProfit >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ number_format(abs($netProfit), 2) }}</span>
    </div>
</div>
@endsection
