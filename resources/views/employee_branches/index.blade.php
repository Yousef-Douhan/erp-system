@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto">
    <!-- رأس الصفحة -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <h2 class="text-xl font-bold text-slate-800">ربط الموظفين بالفروع (Employee Branches)</h2>
            <p class="text-xs text-slate-500">إدارة الفروع التابعة لكل موظف وتحديد الفرع الرئيسي.</p>
        </div>
        
        <button type="button" onclick="openModal('createModal')" 
            class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl shadow-sm transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            ربط موظف بفرع جديد
        </button>
    </div>

    <!-- التنبيهات -->
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
                        <th class="py-3.5 px-6">الموظف</th>
                        <th class="py-3.5 px-6">الفرع</th>
                        <th class="py-3.5 px-6">نوع التبعية</th>
                        <th class="py-3.5 px-6 text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                    @forelse($employeeBranches as $item)
                    <tr class="hover:bg-slate-50/55 transition-colors">
                        <td class="py-4 px-6 text-xs text-slate-400 font-mono">#{{ $item->id }}</td>
                        <td class="py-4 px-6 font-bold text-slate-800">{{ $item->employee->first_name ?? '' }} {{ $item->employee->last_name ?? '' }}</td>
                        <td class="py-4 px-6 text-xs text-slate-600 font-medium">{{ $item->branch->name ?? '---' }}</td>
                        <td class="py-4 px-6">
                            @if($item->is_primary)
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-600 border border-indigo-100">فرع رئيسي</span>
                            @else
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">فرع فرعي / إضافي</span>
                            @endif
                        </td>
                        <td class="py-4 px-6 text-center space-x-2 space-x-reverse">
                            <button onclick="openModal('editModal{{ $item->id }}')" class="px-3 py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-700 rounded-xl text-xs font-semibold">تعديل</button>
                            <form action="{{ route('employee-branches.destroy', $item->id) }}" method="POST" class="inline-block">
                                @csrf @method('DELETE')
                                <button type="submit" onclick="return confirm('هل أنت متأكد من إلغاء الربط؟')" class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-xl text-xs font-semibold">حذف</button>
                            </form>
                        </td>
                    </tr>

                    <!-- مودال التعديل -->
                    <div id="editModal{{ $item->id }}" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
                        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
                            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                                <h3 class="font-bold text-slate-800 text-sm">تعديل ارتباط الفرع</h3>
                                <button onclick="closeModal('editModal{{ $item->id }}')" class="text-slate-400 font-bold">&times;</button>
                            </div>
                            <form action="{{ route('employee-branches.update', $item->id) }}" method="POST">
                                @csrf @method('PUT')
                                <div class="p-6 space-y-4">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 mb-1">الموظف</label>
                                        <select name="employee_id" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs">
                                            @foreach($employees as $emp)
                                                <option value="{{ $emp->id }}" {{ $item->employee_id == $emp->id ? 'selected' : '' }}>{{ $emp->first_name }} {{ $emp->last_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 mb-1">الفرع</label>
                                        <select name="branch_id" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs">
                                            @foreach($branches as $branch)
                                                <option value="{{ $branch->id }}" {{ $item->branch_id == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="flex items-center gap-2 pt-2">
                                        <input type="checkbox" name="is_primary" id="is_primary_{{ $item->id }}" value="1" {{ $item->is_primary ? 'checked' : '' }} class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                                        <label for="is_primary_{{ $item->id }}" class="text-xs font-bold text-slate-700">جعل هذا الفرع هو الفرع الرئيسي للموظف</label>
                                    </div>
                                </div>
                                <div class="px-6 py-4 bg-slate-50 border-t flex justify-end gap-2">
                                    <button type="button" onclick="closeModal('editModal{{ $item->id }}')" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold">إلغاء</button>
                                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-xs font-semibold">تحديث</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    @empty
                    <tr><td colspan="5" class="text-center py-8 text-slate-400 text-xs">لا توجد سجلات ربط فروع للموظفين.</td></tr>
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
            <h3 class="font-bold text-slate-800 text-sm">ربط موظف بفرع جديد</h3>
            <button onclick="closeModal('createModal')" class="text-slate-400 font-bold">&times;</button>
        </div>
        <form action="{{ route('employee-branches.store') }}" method="POST">
            @csrf
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">اختر الموظف</label>
                    <select name="employee_id" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs">
                        <option value="">اختر الموظف...</option>
                        @foreach($employees as $emp)
                            <option value="{{ $emp->id }}">{{ $emp->first_name }} {{ $emp->last_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">اختر الفرع</label>
                    <select name="branch_id" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs">
                        <option value="">اختر الفرع...</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center gap-2 pt-2">
                    <input type="checkbox" name="is_primary" id="is_primary_new" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                    <label for="is_primary_new" class="text-xs font-bold text-slate-700">جعل هذا الفرع هو الفرع الرئيسي للموظف</label>
                </div>
            </div>
            <div class="px-6 py-4 bg-slate-50 border-t flex justify-end gap-2">
                <button type="button" onclick="closeModal('createModal')" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold">إلغاء</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-xs font-semibold">حفظ الارتباط</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
</script>
@endsection