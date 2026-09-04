@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-extrabold text-slate-800">إعدادات حد المخزون (Min / Max)</h2>
            <p class="text-xs text-slate-400 mt-1">تحديد الحد الأدنى والأقصى لكميات المنتجات في كل مستودع للتنبيهات</p>
        </div>
        <button onclick="openModal('addModal')" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl shadow-md transition-all flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            إضافة إعداد جديد
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
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50 text-slate-500 text-xs font-bold text-right">
                <tr>
                    <th class="px-6 py-3">المنتج</th>
                    <th class="px-6 py-3">المستودع</th>
                    <th class="px-6 py-3">الحد الأدنى (Min)</th>
                    <th class="px-6 py-3">الحد الأقصى (Max)</th>
                    <th class="px-6 py-3 text-center">التحكم</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 text-sm text-slate-700">
                @forelse($settings as $setting)
                    <tr>
                        <td class="px-6 py-4 font-semibold text-slate-900">{{ $setting->product->name ?? '---' }}</td>
                        <td class="px-6 py-4">{{ $setting->warehouse->name ?? '---' }}</td>
                        <td class="px-6 py-4 text-amber-600 font-bold">{{ $setting->min_qty }}</td>
                        <td class="px-6 py-4 text-emerald-600 font-bold">{{ $setting->max_qty }}</td>
                        <td class="px-6 py-4 text-center flex items-center justify-center gap-2">
                            <!-- زر التعديل -->
                            <button onclick='openEditModal(@json($setting))' class="text-indigo-600 hover:text-indigo-800 text-xs font-semibold px-2.5 py-1 bg-indigo-50 rounded-lg">تعديل</button>

                            <!-- زر الحذف -->
                            <form action="{{ route('stock-settings.destroy', $setting->id) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من الحذف؟');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-rose-600 hover:text-rose-800 text-xs font-semibold px-2.5 py-1 bg-rose-50 rounded-lg">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-slate-400 text-sm">
                            لا توجد إعدادات مخزون مضافة حتى الآن.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        
        @if($settings->hasPages())
            <div class="p-4 border-t border-slate-200">
                {{ $settings->links() }}
            </div>
        @endif
    </div>
</div>

<!-- Modal إضافة إعداد جديد -->
<div id="addModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 hidden">
    <div class="bg-white rounded-2xl p-6 w-full max-w-lg space-y-4 shadow-xl">
        <h3 class="text-lg font-bold text-slate-800">إضافة إعداد مخزون جديد</h3>
        
        <form action="{{ route('stock-settings.store') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">المنتج</label>
                <select name="product_id" class="w-full rounded-xl border-slate-200 text-sm" required>
                    <option value="">اختر المنتج</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}">{{ $product->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">المستودع</label>
                <select name="warehouse_id" class="w-full rounded-xl border-slate-200 text-sm" required>
                    <option value="">اختر المستودع</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">الحد الأدنى (Min Qty)</label>
                    <input type="number" name="min_qty" class="w-full rounded-xl border-slate-200 text-sm" min="0" required>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">الحد الأقصى (Max Qty)</label>
                    <input type="number" name="max_qty" class="w-full rounded-xl border-slate-200 text-sm" min="0" required>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeModal('addModal')" class="px-4 py-2 bg-slate-100 text-slate-600 text-xs font-semibold rounded-xl">إلغاء</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-xs font-semibold rounded-xl">حفظ</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal تعديل الإعداد -->
<div id="editModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 hidden">
    <div class="bg-white rounded-2xl p-6 w-full max-w-lg space-y-4 shadow-xl">
        <h3 class="text-lg font-bold text-slate-800">تعديل إعداد المخزون</h3>
        
        <form id="editForm" method="POST" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">المنتج</label>
                <select name="product_id" id="edit_product_id" class="w-full rounded-xl border-slate-200 text-sm" required>
                    <option value="">اختر المنتج</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}">{{ $product->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">المستودع</label>
                <select name="warehouse_id" id="edit_warehouse_id" class="w-full rounded-xl border-slate-200 text-sm" required>
                    <option value="">اختر المستودع</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">الحد الأدنى (Min Qty)</label>
                    <input type="number" name="min_qty" id="edit_min_qty" class="w-full rounded-xl border-slate-200 text-sm" min="0" required>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">الحد الأقصى (Max Qty)</label>
                    <input type="number" name="max_qty" id="edit_max_qty" class="w-full rounded-xl border-slate-200 text-sm" min="0" required>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeModal('editModal')" class="px-4 py-2 bg-slate-100 text-slate-600 text-xs font-semibold rounded-xl">إلغاء</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-xs font-semibold rounded-xl">تحديث</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(id) { 
        document.getElementById(id).classList.remove('hidden'); 
    }
    
    function closeModal(id) { 
        document.getElementById(id).classList.add('hidden'); 
    }

    function openEditModal(setting) {
        // تعبئة حقول الفم (Form) بالبيانات القادمة
        document.getElementById('edit_product_id').value = setting.product_id;
        document.getElementById('edit_warehouse_id').value = setting.warehouse_id;
        document.getElementById('edit_min_qty').value = setting.min_qty;
        document.getElementById('edit_max_qty').value = setting.max_qty;

        // ضبط مسار الـ Form للـ Update بناءً على الـ ID
        document.getElementById('editForm').action = `/stock-settings/${setting.id}`;

        // فتح الـ Modal
        openModal('editModal');
    }
</script>
@endsection