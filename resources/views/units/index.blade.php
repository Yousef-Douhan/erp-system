@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <!-- Header & Add Button -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-extrabold text-slate-800">إدارة الوحدات (Units)</h2>
            <p class="text-xs text-slate-400 mt-1">إضافة وإدارة وحدات القياس والاختصارات للمنتجات والأدوية</p>
        </div>
        <button onclick="openModal('addModal')" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl shadow-md shadow-indigo-100 transition-all flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            إضافة وحدة جديدة
        </button>
    </div>

    <!-- Alert Success -->
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 text-emerald-600 text-xs font-semibold border border-emerald-100">
            {{ session('success') }}
        </div>
    @endif

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="w-full text-right border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-500 uppercase">
                    <th class="p-4">#</th>
                    <th class="p-4">اسم الوحدة</th>
                    <th class="p-4">الاختصار</th>
                    <th class="p-4">الوصف</th>
                    <th class="p-4">الحالة</th>
                    <th class="p-4 text-left">الإجراءات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm text-slate-600">
                @forelse($units as $unit)
                    <tr class="hover:bg-slate-50/50 transition-all">
                        <td class="p-4 font-semibold text-slate-400">{{ $loop->iteration }}</td>
                        <td class="p-4 font-bold text-slate-800">{{ $unit->name }}</td>
                        <td class="p-4">
                            <span class="px-2.5 py-1 bg-slate-100 text-slate-700 rounded-lg text-xs font-mono font-bold">
                                {{ $unit->short_name ?? '-' }}
                            </span>
                        </td>
                        <td class="p-4 text-xs text-slate-500">{{ Str::limit($unit->description, 30) ?: '-' }}</td>
                        <td class="p-4">
                            @if($unit->is_active)
                                <span class="px-2.5 py-1 bg-emerald-50 text-emerald-600 rounded-lg text-xs font-bold">نشط</span>
                            @else
                                <span class="px-2.5 py-1 bg-rose-50 text-rose-600 rounded-lg text-xs font-bold">غير نشط</span>
                            @endif
                        </td>
                        <td class="p-4 text-left space-x-2 space-x-reverse">
                            <button onclick='openEditModal(@json($unit))' class="text-indigo-600 hover:text-indigo-800 text-xs font-semibold bg-indigo-50 px-3 py-1.5 rounded-lg transition-all">تعديل</button>
                            <form action="{{ route('units.destroy', $unit->id) }}" method="POST" class="inline-block" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-rose-600 hover:text-rose-800 text-xs font-semibold bg-rose-50 px-3 py-1.5 rounded-lg transition-all">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-8 text-center text-slate-400 text-xs">لا توجد وحدات مضافة حتى الآن.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4 border-t border-slate-100">
            {{ $units->links() }}
        </div>
    </div>
</div>

<!-- Add Modal -->
<div id="addModal" class="fixed inset-0 bg-slate-900/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 shadow-xl">
        <h3 class="text-base font-bold text-slate-800">إضافة وحدة قياس جديدة</h3>
        <form action="{{ route('units.store') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">اسم الوحدة</label>
                <input type="text" name="name" required class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">الاختصار (Short Name)</label>
                <input type="text" name="short_name" class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600" placeholder="مثل: box, strip">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">الوصف</label>
                <textarea name="description" rows="2" class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600"></textarea>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_active" id="add_is_active" value="1" checked class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-600">
                <label for="add_is_active" class="text-xs font-bold text-slate-600">نشط</label>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeModal('addModal')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold rounded-xl">إلغاء</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl">حفظ</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 bg-slate-900/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 shadow-xl">
        <h3 class="text-base font-bold text-slate-800">تعديل الوحدة</h3>
        <form id="editForm" method="POST" class="space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">اسم الوحدة</label>
                <input type="text" name="name" id="edit_name" required class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">الاختصار (Short Name)</label>
                <input type="text" name="short_name" id="edit_short_name" class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">الوصف</label>
                <textarea name="description" id="edit_description" rows="2" class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-600 focus:ring-indigo-600"></textarea>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_active" id="edit_is_active" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-600">
                <label for="edit_is_active" class="text-xs font-bold text-slate-600">نشط</label>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeModal('editModal')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold rounded-xl">إلغاء</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl">تحديث</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
    function openEditModal(unit) {
        document.getElementById('edit_name').value = unit.name;
        document.getElementById('edit_short_name').value = unit.short_name || '';
        document.getElementById('edit_description').value = unit.description || '';
        document.getElementById('edit_is_active').checked = unit.is_active == 1;
        document.getElementById('editForm').action = `/units/${unit.id}`;
        openModal('editModal');
    }
</script>
@endsection