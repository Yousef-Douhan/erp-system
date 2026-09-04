@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <!-- Header & Back Button -->
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2">
                <h2 class="text-xl font-extrabold text-slate-800">إدارة جميع الدفعات (Batches)</h2>
            </div>
            <p class="text-xs text-slate-400 mt-1">تتبع تواريخ الصلاحية، أرقام التشغيلات، تكاليف الشراء، ومستويات المخزون لجميع المنتجات</p>
        </div>
        <button onclick="openModal('addBatchModal')" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl shadow-md shadow-indigo-100 transition-all flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            إضافة دفعة جديدة
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

    <!-- Table of Batches -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50 text-slate-500 text-xs font-bold text-right">
                    <tr>
                        <th class="px-6 py-3">رقم الدفعة</th>
                        <th class="px-6 py-3">المنتج</th>
                        <th class="px-6 py-3">المستودع</th>
                        <th class="px-6 py-3">الكمية</th>
                        <th class="px-6 py-3">سعر التكلفة</th>
                        <th class="px-6 py-3">تاريخ الإنتاج</th>
                        <th class="px-6 py-3">تاريخ الانتهاء</th>
                        <th class="px-6 py-3 text-center">التحكم</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 text-sm text-slate-700">
                    @forelse($batches as $batch)
                        <tr>
                            <td class="px-6 py-4 font-semibold text-slate-900">{{ $batch->batch_number }}</td>
                            <td class="px-6 py-4">{{ $batch->product->name ?? '---' }}</td>
                            <td class="px-6 py-4">{{ $batch->warehouse->name ?? '---' }}</td>
                            <td class="px-6 py-4 font-bold text-indigo-600">{{ $batch->quantity }}</td>
                            <td class="px-6 py-4">{{ $batch->cost_price }}</td>
                            <td class="px-6 py-4">{{ $batch->manufacturing_date ?? '---' }}</td>
                            <td class="px-6 py-4">{{ $batch->expiry_date ?? '---' }}</td>
                            <td class="px-6 py-4 text-center">
                                <!-- أزرار الحذف -->
                                <form action="{{ route('batches.destroy', $batch->id) }}" method="POST" class="inline" onsubmit="return confirm('هل أنت متأكد من حذف هذه الدفعة؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-rose-600 hover:text-rose-800 text-xs font-semibold px-2 py-1 bg-rose-50 rounded-lg">حذف</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-8 text-center text-slate-400 text-sm">
                                لا توجد أي دفعات مضافة حتى الآن.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        @if($batches->hasPages())
            <div class="p-4 border-t border-slate-200">
                {{ $batches->links() }}
            </div>
        @endif
    </div>
</div>

<!-- Modal إضافة دفعة -->
<div id="addBatchModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 hidden">
    <div class="bg-white rounded-2xl p-6 w-full max-w-lg space-y-4 shadow-xl">
        <h3 class="text-lg font-bold text-slate-800">إضافة دفعة جديدة</h3>
        
        <form action="{{ route('batches.store') }}" method="POST" class="space-y-4">
            @csrf
            
            <!-- اختيار المنتج -->
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">المنتج</label>
                <select name="product_id" class="w-full rounded-xl border-slate-200 text-sm" required>
                    <option value="">اختر المنتج</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}">{{ $product->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- اختيار المستودع -->
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">المستودع</label>
                <select name="warehouse_id" class="w-full rounded-xl border-slate-200 text-sm" required>
                    <option value="">اختر المستودع</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- رقم الدفعة -->
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">رقم الدفعة (Batch Number)</label>
                <input type="text" name="batch_number" class="w-full rounded-xl border-slate-200 text-sm" required>
            </div>

            <!-- تواريخ الإنتاج والانتهاء -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">تاريخ الإنتاج</label>
                    <input type="date" name="manufacturing_date" class="w-full rounded-xl border-slate-200 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">تاريخ الانتهاء</label>
                    <input type="date" name="expiry_date" class="w-full rounded-xl border-slate-200 text-sm">
                </div>
            </div>

            <!-- سعر التكلفة والكمية -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">سعر التكلفة</label>
                    <input type="number" step="0.01" name="cost_price" class="w-full rounded-xl border-slate-200 text-sm" required>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">الكمية</label>
                    <input type="number" name="quantity" class="w-full rounded-xl border-slate-200 text-sm" required>
                </div>
            </div>

            <!-- أزرار الإجراءات -->
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeModal('addBatchModal')" class="px-4 py-2 bg-slate-100 text-slate-600 text-xs font-semibold rounded-xl">إلغاء</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-xs font-semibold rounded-xl">حفظ الدفعة</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
</script>
@endsection