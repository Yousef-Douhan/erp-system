<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-extrabold text-xl text-slate-800 leading-tight">
                سجل حركات المخزون
            </h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('inventory.transactions.stock-in') }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-100 transition-all">
                    + إدخال بضاعة (Stock-In)
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-2">
        <div class="max-w-7xl mx-auto space-y-4">

            @if(session('success'))
                <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-2xl text-xs font-semibold">
                    {{ session('success') }}
                </div>
            @endif

            <!-- جدول الحركات -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-right border-collapse">
                        <thead>
                            <tr class="bg-slate-50/70 border-b border-slate-200 text-slate-500 text-[11px] font-bold uppercase tracking-wider">
                                <th class="py-3 px-4">#</th>
                                <th class="py-3 px-4">نوع الحركة</th>
                                <th class="py-3 px-4">رقم المرجع</th>
                                <th class="py-3 px-4">المستخدم</th>
                                <th class="py-3 px-4">ملاحظات</th>
                                <th class="py-3 px-4">التاريخ والوقت</th>
                                <th class="py-3 px-4 text-center">التفاصيل / العناصر</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                            @forelse($transactions as $transaction)
                                <tr class="hover:bg-slate-50/50 transition-all">
                                    <td class="py-3.5 px-4 font-bold text-slate-400">#{{ $transaction->id }}</td>
                                    <td class="py-3.5 px-4">
                                        @if($transaction->type == 'in')
                                            <span class="px-2.5 py-1 bg-emerald-50 text-emerald-600 rounded-lg text-[10px] font-bold">
                                                وارد (Stock-In)
                                            </span>
                                        @elseif($transaction->type == 'out')
                                            <span class="px-2.5 py-1 bg-rose-50 text-rose-600 rounded-lg text-[10px] font-bold">
                                                صادر (Stock-Out)
                                            </span>
                                        @else
                                            <span class="px-2.5 py-1 bg-amber-50 text-amber-600 rounded-lg text-[10px] font-bold">
                                                تسوية (Adjustment)
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4 font-semibold text-slate-800">{{ $transaction->reference_number ?? '---' }}</td>
                                    <td class="py-3.5 px-4 text-slate-600">{{ $transaction->user->name ?? 'مستخدم غير معروف' }}</td>
                                    <td class="py-3.5 px-4 text-slate-500 max-w-xs truncate">{{ $transaction->notes ?? '---' }}</td>
                                    <td class="py-3.5 px-4 text-slate-400 text-[11px]">{{ $transaction->created_at->format('Y-m-d | h:i A') }}</td>
                                    <td class="py-3.5 px-4 text-center">
                                        <div class="inline-flex flex-col gap-1 text-right bg-slate-50 p-2 rounded-xl border border-slate-100 min-w-[200px]">
                                            @foreach($transaction->items as $item)
                                                <div class="flex justify-between items-center text-[11px]">
                                                    <span class="font-bold text-slate-700">{{ $item->product->name ?? 'منتج محذوف' }}</span>
                                                    <span class="bg-indigo-50 text-indigo-600 px-1.5 py-0.5 rounded font-mono font-bold">+{{ $item->quantity }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-8 text-slate-400 text-xs">
                                        لا توجد حركات مخزنية مسجلة حتى الآن.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- التصفح (Pagination) -->
                @if($transactions->hasPages())
                    <div class="p-4 border-t border-slate-100">
                        {{ $transactions->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>