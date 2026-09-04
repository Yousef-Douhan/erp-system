@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto">
    <!-- رأس الصفحة مع زر الإضافة -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <h2 class="text-xl font-bold text-slate-800">إدارة الموظفين (Employees)</h2>
            <p class="text-xs text-slate-500">إدارة سجلات الموظفين والرواتب والتابع للمنظمات.</p>
        </div>
        
        <button type="button" onclick="openModal('createModal')" 
            class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl shadow-sm transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            إضافة موظف جديد
        </button>
    </div>

    <!-- رسائل التنبيه والخطأ -->
    @if(session('success'))
        <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-2xl text-xs font-medium flex items-center justify-between">
            <span>{{ session('success') }}</span>
            <button onclick="this.parentElement.remove()" class="text-emerald-500 font-bold">&times;</button>
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6 p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-2xl text-xs font-medium">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- الجدول -->
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-right border-collapse">
                <thead>
                    <tr class="bg-slate-50/70 border-b border-slate-200 text-slate-500 text-xs font-semibold uppercase tracking-wider">
                        <th class="py-3.5 px-6">#</th>
                        <th class="py-3.5 px-6">اسم الموظف</th>
                        <th class="py-3.5 px-6">المنظمة</th>
                        <th class="py-3.5 px-6">الراتب</th>
                        <th class="py-3.5 px-6">الحالة</th>
                        <th class="py-3.5 px-6 text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                    @forelse($employees as $emp)
                    <tr class="hover:bg-slate-50/55 transition-colors">
                        <td class="py-4 px-6 text-xs text-slate-400 font-mono">#{{ $emp->id }}</td>
                        <td class="py-4 px-6 font-bold text-slate-800">{{ $emp->first_name }} {{ $emp->last_name }}</td>
                        <td class="py-4 px-6 text-xs text-slate-600 font-medium">{{ $emp->organization->name ?? '---' }}</td>
                        <td class="py-4 px-6 font-mono text-xs font-semibold text-slate-700">{{ number_format($emp->salary, 2) }}</td>
                        <td class="py-4 px-6">
                            @if($emp->status == 'active')
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-600 border border-emerald-100">نشط</span>
                            @elseif($emp->status == 'inactive')
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-50 text-amber-600 border border-amber-100">غير نشط</span>
                            @else
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-50 text-rose-600 border border-rose-100">منتهي</span>
                            @endif
                        </td>
                        <td class="py-4 px-6 text-center space-x-2 space-x-reverse">
                            <button onclick="openModal('editModal{{ $emp->id }}')" class="px-3 py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-700 rounded-xl text-xs font-semibold">تعديل</button>
                            <form action="{{ route('employees.destroy', $emp->id) }}" method="POST" class="inline-block">
                                @csrf @method('DELETE')
                                <button type="submit" onclick="return confirm('هل أنت متأكد من الحذف؟')" class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-xl text-xs font-semibold">حذف</button>
                            </form>
                        </td>
                    </tr>

                    <!-- مودال التعديل -->
                    <div id="editModal{{ $emp->id }}" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
                        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
                            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                                <h3 class="font-bold text-slate-800 text-sm">تعديل موظف: {{ $emp->first_name }}</h3>
                                <button onclick="closeModal('editModal{{ $emp->id }}')" class="text-slate-400 font-bold">&times;</button>
                            </div>
                            <form action="{{ route('employees.update', $emp->id) }}" method="POST">
                                @csrf @method('PUT')
                                <div class="p-6 space-y-4">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 mb-1">المنظمة</label>
                                        <select name="organization_id" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs">
                                            @foreach($organizations as $org)
                                                <option value="{{ $org->id }}" {{ $emp->organization_id == $org->id ? 'selected' : '' }}>{{ $org->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label class="block text-xs font-bold text-slate-700 mb-1">الاسم الأول</label>
                                            <input type="text" name="first_name" value="{{ $emp->first_name }}" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-slate-700 mb-1">اسم العائلة</label>
                                            <input type="text" name="last_name" value="{{ $emp->last_name }}" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 mb-1">الراتب</label>
                                        <input type="number" step="0.01" name="salary" value="{{ $emp->salary }}" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 mb-1">الحالة</label>
                                        <select name="status" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs">
                                            <option value="active" {{ $emp->status == 'active' ? 'selected' : '' }}>نشط</option>
                                            <option value="inactive" {{ $emp->status == 'inactive' ? 'selected' : '' }}>غير نشط</option>
                                            <option value="terminated" {{ $emp->status == 'terminated' ? 'selected' : '' }}>منتهي</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="px-6 py-4 bg-slate-50 border-t flex justify-end gap-2">
                                    <button type="button" onclick="closeModal('editModal{{ $emp->id }}')" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold">إلغاء</button>
                                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-xs font-semibold">تحديث</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    @empty
                    <tr><td colspan="6" class="text-center py-8 text-slate-400 text-xs">لا توجد سجلات موظفين.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- مودال الإنشاء -->
<div id="createModal" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
            <h3 class="font-bold text-slate-800 text-sm">إضافة موظف جديد</h3>
            <button onclick="closeModal('createModal')" class="text-slate-400 font-bold">&times;</button>
        </div>
        <form action="{{ route('employees.store') }}" method="POST">
            @csrf
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">المنظمة التابع لها</label>
                    <select name="organization_id" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs">
                        <option value="">اختر المنظمة...</option>
                        @foreach($organizations as $org)
                            <option value="{{ $org->id }}">{{ $org->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">الاسم الأول</label>
                        <input type="text" name="first_name" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs" placeholder="محمد">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">اسم العائلة</label>
                        <input type="text" name="last_name" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs" placeholder="أحمد">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">الراتب</label>
                    <input type="number" step="0.01" name="salary" value="0.00" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">الحالة</label>
                    <select name="status" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs">
                        <option value="active">نشط</option>
                        <option value="inactive">غير نشط</option>
                        <option value="terminated">منتهي</option>
                    </select>
                </div>
            </div>
            <div class="px-6 py-4 bg-slate-50 border-t flex justify-end gap-2">
                <button type="button" onclick="closeModal('createModal')" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold">إلغاء</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-xs font-semibold">حفظ الموظف</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
</script>
@endsection