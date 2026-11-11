@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex justify-between items-start">
        <div>
            <h3 class="text-base font-black text-slate-800">كشف حساب المورد: {{ $supplierAccount->supplier->name ?? '-' }}</h3>
            <p class="text-xs text-slate-500 mt-1">الرصيد الحالي: <b class="text-indigo-600">{{ number_format($supplierAccount->supplier->balance ?? 0, 2) }}</b></p>
        </div>
        <a href="{{ route('supplier-accounts.index') }}" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-xl text-xs font-bold hover:bg-slate-200">رجوع</a>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <table class="w-full text-right text-xs">
            <thead>
                <tr class="bg-slate-50 text-slate-500 border-b border-slate-200">
                    <th class="p-3 font-bold">التاريخ</th>
                    <th class="p-3 font-bold">نوع الحركة</th>
                    <th class="p-3 font-bold">مدين</th>
                    <th class="p-3 font-bold">دائن</th>
                    <th class="p-3 font-bold">الرصيد بعد الحركة</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($statement as $row)
                    <tr class="{{ $row->id === $supplierAccount->id ? 'bg-indigo-50/50' : '' }}">
                        <td class="p-3 text-slate-600">{{ $row->created_at }}</td>
                        <td class="p-3 text-slate-800">{{ $row->transaction_type }}</td>
                        <td class="p-3 font-bold">{{ number_format($row->debit, 2) }}</td>
                        <td class="p-3 font-bold">{{ number_format($row->credit, 2) }}</td>
                        <td class="p-3 font-black text-indigo-600">{{ number_format($row->balance, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-6 text-center text-slate-400">لا توجد حركات.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4">{{ $statement->links() }}</div>
    </div>
</div>
@endsection
