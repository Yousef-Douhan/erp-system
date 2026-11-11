<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4" dir="rtl">
            <div>
                <h2 class="font-extrabold text-2xl text-slate-800 tracking-tight flex items-center gap-3">
                    <span class="p-2 bg-emerald-50 text-emerald-600 rounded-xl shadow-sm">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                    </span>
                    إدارة المخزون
                </h2>
                <p class="text-xs text-slate-500 mt-1 mr-11">متابعة المنتجات، الكميات المتوفرة، وحركة المخزن</p>
            </div>
            
            <!-- ربط زر إضافة منتج جديد -->
            <a href="{{ route('inventory.create') }}" class="inline-flex items-center gap-2 px-6 py-3 bg-indigo-600 text-white font-medium text-sm rounded-xl hover:bg-indigo-700 shadow-md shadow-indigo-200 transition-all">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                إضافة منتج جديد
            </a>
        </div>
    </x-slot>

    @include('partials.flash', ['withSuccess' => true])

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
        <table class="w-full text-right border-collapse">
            <thead>
                <tr class="bg-slate-50 text-slate-400 text-xs uppercase tracking-wider">
                    <th class="px-6 py-4 font-bold">اسم المنتج</th>
                    <th class="px-6 py-4 font-bold">الفئة</th>
                    <th class="px-6 py-4 font-bold">الكمية</th>
                    <th class="px-6 py-4 font-bold">سعر البيع</th>
                    <th class="px-6 py-4 font-bold text-left">الإجراءات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($items as $item)
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-6 py-4 text-sm font-semibold text-slate-800">{{ $item->name }}</td>
                        <td class="px-6 py-4 text-sm text-slate-600">{{ $item->category->name ?? 'غير مصنف' }}</td>
                        <td class="px-6 py-4">
                            <span class="px-2.5 py-1 {{ $item->stock_quantity < 10 ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700' }} rounded-lg text-xs font-bold">
                                {{ $item->stock_quantity }} وحدة
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm font-medium text-slate-700">{{ number_format($item->selling_price, 2) }} شيكل</td>
                        <td class="px-6 py-4 text-left">
                            <div class="flex items-center justify-end gap-2">
                                <!-- ربط زر التعديل -->
                                <a href="{{ route('inventory.edit', $item->id) }}" class="p-2 text-slate-400 hover:text-indigo-600 transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                                <!-- ربط زر الحذف -->
                                <form action="{{ route('inventory.destroy', $item->id) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من حذف المنتج؟')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-2 text-slate-400 hover:text-rose-600 transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-slate-400 text-sm">
                            لا توجد منتجات مضافة في المخزن حالياً.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>