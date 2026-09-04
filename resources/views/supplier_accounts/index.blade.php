@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 h-fit">
        <h3 class="text-base font-black text-slate-800 mb-4">تسجيل حركة مورد</h3>

        {{-- عرض أخطاء التحقق إن وجدت --}}
        @if ($errors->any())
            <div class="mb-4 p-3 bg-rose-50 text-rose-600 rounded-xl text-xs">
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- رسالة النجاح إن وجدت --}}
        @if (session('success'))
            <div class="mb-4 p-3 bg-emerald-50 text-emerald-600 rounded-xl text-xs font-bold">
                {{ session('success') }}
            </div>
        @endif

        <form action="{{ route('supplier-accounts.store') }}" method="POST" class="space-y-4">
            @csrf
            
            {{-- اختيار المورد بالاسم عبر القائمة المنسدلة --}}
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">اختر المورد</label>
                <select name="supplier_id" id="supplier_id" required class="w-full px-4 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 outline-none bg-white">
                    <option value="">-- اختر المورد --</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->name ?? $supplier->company_name ?? 'مورد #' . $supplier->id }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">نوع المعاملة</label>
                <input type="text" name="transaction_type" required placeholder="مثال: purchase, payment" class="w-full px-4 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 outline-none">
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">مدين</label>
                    <input type="number" step="0.01" name="debit" value="0.00" required class="w-full px-4 py-2 rounded-xl border border-slate-200 text-xs font-mono focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">دائن</label>
                    <input type="number" step="0.01" name="credit" value="0.00" required class="w-full px-4 py-2 rounded-xl border border-slate-200 text-xs font-mono focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">الرصيد التراكمي (يُحسب تلقائياً)</label>
                <input type="number" step="0.01" name="balance" value="0.00" readonly class="w-full px-4 py-2 rounded-xl border border-slate-200 bg-slate-50 text-xs font-mono text-slate-400 outline-none cursor-not-allowed">
            </div>
            <button type="submit" class="w-full py-2.5 bg-indigo-600 text-white rounded-xl text-xs font-bold hover:bg-indigo-700 shadow-md transition">حفظ الحركة</button>
        </form>
    </div>

    <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h3 class="text-base font-black text-slate-800">حركات حسابات الموردين</h3>
            <span class="text-xs text-slate-400 font-semibold">الإجمالي: {{ $supplierAccounts->count() }}</span>
        </div>
        <table class="w-full text-right border-collapse text-xs">
            <thead>
                <tr class="bg-slate-50 text-slate-500 border-b border-slate-200">
                    <th class="p-4 font-bold">اسم المورد</th>
                    <th class="p-4 font-bold">النوع</th>
                    <th class="p-4 font-bold">مدين</th>
                    <th class="p-4 font-bold">دائن</th>
                    <th class="p-4 font-bold">الرصيد</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($supplierAccounts as $item)
                <tr class="hover:bg-slate-50/50">
                    {{-- عرض اسم المورد من العلاقة --}}
                    <td class="p-4 font-bold text-slate-800">
                        {{ $item->supplier->name ?? $item->supplier->company_name ?? 'مورد #' . $item->supplier_id }}
                    </td>
                    <td class="p-4 text-slate-600">{{ $item->transaction_type }}</td>
                    <td class="p-4 font-mono text-emerald-600 font-bold">{{ number_format($item->debit, 2) }}</td>
                    <td class="p-4 font-mono text-rose-600 font-bold">{{ number_format($item->credit, 2) }}</td>
                    <td class="p-4 font-mono text-indigo-600 font-bold">{{ number_format($item->balance, 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="p-6 text-center text-slate-400">لا توجد حركات مسجلة.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection