@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <!-- Header & Back Button -->
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('products.units.index', $product->id) }}" class="text-xs text-indigo-600 hover:underline">← العودة لوحدات المنتج</a>
            </div>
            <h2 class="text-xl font-extrabold text-slate-800 mt-1">إدارة باركودات الوحدة: <span class="text-indigo-600">{{ $unit->unit->name ?? '' }}</span></h2>
            <p class="text-xs text-slate-400 mt-1">المنتج الأساسي: {{ $product->name }}</p>
        </div>
        <button onclick="openModal('addBarcodeModal')" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl shadow-md transition-all flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            إضافة باركود جديد
        </button>
    </div>

    <!-- Alert Success -->
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 text-emerald-600 text-xs font-semibold border border-emerald-100">
            {{ session('success') }}
        </div>
    @endif

    <!-- Errors Validation -->
    @if($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 text-rose-600 text-xs font-semibold border border-rose-100">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="w-full text-right border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-500 uppercase">
                    <th class="p-4">#</th>
                    <th class="p-4">الباركود</th>
                    <th class="p-4">نوع الباركود</th>
                    <th class="p-4">تاريخ الإضافة</th>
                    <th class="p-4 text-left">الإجراءات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm text-slate-600">
                @forelse($barcodes as $barcode)
                    <tr class="hover:bg-slate-50/50 transition-all">
                        <td class="p-4 font-semibold text-slate-400">{{ $loop->iteration }}</td>
                        <td class="p-4 font-mono font-bold text-slate-800">{{ $barcode->barcode }}</td>
                        <td class="p-4 text-xs">
                            <span class="px-2.5 py-1 bg-slate-100 rounded-lg font-semibold">{{ $barcode->type ?? 'عادي' }}</span>
                        </td>
                        <td class="p-4 text-xs text-slate-400">{{ $barcode->created_at->format('Y-m-d H:i') }}</td>
                        <td class="p-4 text-left space-x-1 space-x-reverse whitespace-nowrap">
                            <!-- زر التعديل -->
                            <button onclick='openEditModal(@json($barcode))' class="px-3 py-1 bg-blue-50 text-blue-600 hover:bg-blue-100 text-xs font-semibold rounded-lg transition-all ml-1">
                                تعديل
                            </button>

                            <!-- زر الحذف -->
                            <form action="{{ route('products.units.barcodes.destroy', [$product->id, $unit->id, $barcode->id]) }}" method="POST" class="inline-block" onsubmit="return confirm('هل أنت متأكد من حذف هذا الباركود؟')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="px-3 py-1 bg-rose-50 text-rose-600 hover:bg-rose-100 text-xs font-semibold rounded-lg transition-all">
                                    حذف
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-8 text-center text-slate-400 text-xs">لا توجد باركودات مضافة لهذه الوحدة حتى الآن.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if(method_exists($barcodes, 'links'))
            <div class="p-4 border-t border-slate-100">
                {{ $barcodes->links() }}
            </div>
        @endif
    </div>
</div>

<!-- Modal إضافة باركود -->
<div id="addBarcodeModal" class="fixed inset-0 bg-slate-900/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 shadow-xl">
        <h3 class="text-base font-bold text-slate-800">إضافة باركود جديد للوحدة</h3>
        <form action="{{ route('products.units.barcodes.store', [$product->id, $unit->id]) }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">الباركود *</label>
                <input type="text" name="barcode" required class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600 font-mono">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">نوع الباركود (اختياري)</label>
                <input type="text" name="type" placeholder="مثال: EAN-13" class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600">
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeModal('addBarcodeModal')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold rounded-xl">إلغاء</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl">حفظ</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal تعديل باركود -->
<div id="editBarcodeModal" class="fixed inset-0 bg-slate-900/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 shadow-xl">
        <h3 class="text-base font-bold text-slate-800">تعديل الباركود</h3>
        <form id="editBarcodeForm" method="POST" class="space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">الباركود *</label>
                <input type="text" id="edit_barcode_input" name="barcode" required class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600 font-mono">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">نوع الباركود</label>
                <input type="text" id="edit_type_input" name="type" class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600">
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeModal('editBarcodeModal')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold rounded-xl">إلغاء</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl">تحديث</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

    function openEditModal(barcode) {
        // ضبط مسار الـ Form ديناميكياً
        document.getElementById('editBarcodeForm').action = `/products/{{ $product->id }}/units/{{ $unit->id }}/barcodes/` + barcode.id;
        
        // تعبئة البيانات
        document.getElementById('edit_barcode_input').value = barcode.barcode || '';
        document.getElementById('edit_type_input').value = barcode.type || '';

        openModal('editBarcodeModal');
    }
</script>
@endsection