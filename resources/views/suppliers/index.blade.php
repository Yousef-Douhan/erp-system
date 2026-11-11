@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-extrabold text-slate-800">إدارة الموردين (Suppliers)</h2>
            <p class="text-xs text-slate-400 mt-1">متابعة حسابات الموردين، أرصدتهم، والشركات التابعين لها</p>
        </div>
        <button onclick="openModal('addModal')" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl shadow-md transition-all flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            إضافة مورد جديد
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
                    <th class="px-6 py-3">اسم المسؤول</th>
                    <th class="px-6 py-3">اسم الشركة</th>
                    <th class="px-6 py-3">الصيدلية</th>
                    <th class="px-6 py-3">الرصيد (Balance)</th>
                    <th class="px-6 py-3">الحد الائتماني (Credit Limit)</th>
                    <th class="px-6 py-3 text-center">التحكم</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 text-sm text-slate-700">
                @forelse($suppliers as $supplier)
                    <tr>
                        <td class="px-6 py-4 font-semibold text-slate-900">{{ $supplier->name }}</td>
                        <td class="px-6 py-4">{{ $supplier->company_name ?? '---' }}</td>
                        <td class="px-6 py-4">{{ $supplier->organization->name ?? '---' }}</td>
                        <td class="px-6 py-4 font-bold {{ $supplier->balance > $supplier->credit_limit ? 'text-rose-600' : 'text-slate-700' }}">
                            {{ number_format($supplier->balance, 2) }}
                        </td>
                        <td class="px-6 py-4 font-bold text-indigo-600">{{ number_format($supplier->credit_limit, 2) }}</td>
                        <td class="px-6 py-4 text-center flex items-center justify-center gap-2">
                            <!-- زر التعديل -->
                            <button onclick='openEditModal(@json($supplier))' class="text-indigo-600 hover:text-indigo-800 text-xs font-semibold px-2.5 py-1 bg-indigo-50 rounded-lg">تعديل</button>

                            <!-- زر الحذف -->
                            <form action="{{ route('suppliers.destroy', $supplier->id) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من حذف المورد؟');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-rose-600 hover:text-rose-800 text-xs font-semibold px-2.5 py-1 bg-rose-50 rounded-lg">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-slate-400 text-sm">
                            لا يوجد موردون مضافون حتى الآن.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        
        @if($suppliers->hasPages())
            <div class="p-4 border-t border-slate-200">
                {{ $suppliers->links() }}
            </div>
        @endif
    </div>
</div>

<!-- Modal إضافة مورد جديد -->
<div id="addModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 hidden">
    <div class="bg-white rounded-2xl p-6 w-full max-w-lg space-y-4 shadow-xl">
        <h3 class="text-lg font-bold text-slate-800">إضافة مورد جديد</h3>
        
        <form action="{{ route('suppliers.store') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">الصيدلية</label>
                <select name="organization_id" class="w-full rounded-xl border-slate-200 text-sm" required>
                    <option value="">اختر الصيدلية</option>
                    @foreach($organizations as $org)
                        <option value="{{ $org->id }}">{{ $org->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">اسم المسؤول</label>
                <input type="text" name="name" class="w-full rounded-xl border-slate-200 text-sm" required>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">اسم الشركة / المؤسسة</label>
                <input type="text" name="company_name" class="w-full rounded-xl border-slate-200 text-sm">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">الرصيد الحالي (Balance)</label>
                    <input type="number" step="0.01" name="balance" value="0" class="w-full rounded-xl border-slate-200 text-sm" min="0" required>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">الحد الائتماني (Credit Limit)</label>
                    <input type="number" step="0.01" name="credit_limit" value="0" class="w-full rounded-xl border-slate-200 text-sm" min="0" required>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeModal('addModal')" class="px-4 py-2 bg-slate-100 text-slate-600 text-xs font-semibold rounded-xl">إلغاء</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-xs font-semibold rounded-xl">حفظ</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal تعديل المورد -->
<div id="editModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 hidden">
    <div class="bg-white rounded-2xl p-6 w-full max-w-lg space-y-4 shadow-xl">
        <h3 class="text-lg font-bold text-slate-800">تعديل بيانات المورد</h3>
        
        <form id="editForm" method="POST" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">الصيدلية</label>
                <select name="organization_id" id="edit_organization_id" class="w-full rounded-xl border-slate-200 text-sm" required>
                    <option value="">اختر الصيدلية</option>
                    @foreach($organizations as $org)
                        <option value="{{ $org->id }}">{{ $org->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">اسم المسؤول</label>
                <input type="text" name="name" id="edit_name" class="w-full rounded-xl border-slate-200 text-sm" required>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">اسم الشركة / المؤسسة</label>
                <input type="text" name="company_name" id="edit_company_name" class="w-full rounded-xl border-slate-200 text-sm">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">الرصيد الحالي (Balance)</label>
                    <input type="number" step="0.01" name="balance" id="edit_balance" class="w-full rounded-xl border-slate-200 text-sm" min="0" required>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">الحد الائتماني (Credit Limit)</label>
                    <input type="number" step="0.01" name="credit_limit" id="edit_credit_limit" class="w-full rounded-xl border-slate-200 text-sm" min="0" required>
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
    function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

    function openEditModal(supplier) {
        document.getElementById('edit_organization_id').value = supplier.organization_id;
        document.getElementById('edit_name').value = supplier.name;
        document.getElementById('edit_company_name').value = supplier.company_name ?? '';
        document.getElementById('edit_balance').value = supplier.balance;
        document.getElementById('edit_credit_limit').value = supplier.credit_limit;

        document.getElementById('editForm').action = `/suppliers/${supplier.id}`;
        openModal('editModal');
    }
</script>
@endsection