@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto">@include('partials.subnav', ['set' => 'org'])</div>
<div class="max-w-7xl mx-auto">
    <!-- رأس الصفحة مع زر الإضافة -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <h2 class="text-xl font-bold text-slate-800">إدارة الصيدليات </h2>
            <p class="text-xs text-slate-500">إدارة الشركات والكيانات الرئيسية المرتبطة بالنظام.</p>
        </div>
        
        @if (\App\Support\Tenancy::isSuperAdmin())
        <!-- زر فتح نافذة الإضافة -->
        <button type="button" onclick="openModal('createModal')" 
            class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl shadow-sm shadow-indigo-100 transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            إضافة صيدلية جديدة
        </button>
        @endif
    </div>

    <!-- رسائل التنبيه والخطأ -->
    @if(session('success'))
        <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-2xl text-xs font-medium flex items-center justify-between">
            <span>{{ session('success') }}</span>
            <button onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700 font-bold">&times;</button>
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

    <!-- جدول عرض الصيدليات -->
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-right border-collapse">
                <thead>
                    <tr class="bg-slate-50/70 border-b border-slate-200 text-slate-500 text-xs font-semibold uppercase tracking-wider">
                        <th class="py-3.5 px-6">#</th>
                        <th class="py-3.5 px-6">اسم الصيدلية</th>
                        <th class="py-3.5 px-6">العملة</th>
                        <th class="py-3.5 px-6">المنطقة الزمنية</th>
                        <th class="py-3.5 px-6">الحالة</th>
                        <th class="py-3.5 px-6 text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                    @forelse($organizations as $org)
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="py-4 px-6 text-xs text-slate-400 font-mono">#{{ $org->id }}</td>
                        <td class="py-4 px-6 font-bold text-slate-800">{{ $org->name }}</td>
                        <td class="py-4 px-6"><span class="px-2.5 py-1 bg-slate-100 rounded-lg text-xs font-semibold text-slate-600">{{ $org->currency }}</span></td>
                        <td class="py-4 px-6 text-xs text-slate-500">{{ $org->timezone }}</td>
                        <td class="py-4 px-6">
                            @if($org->status == 'active')
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-600 border border-emerald-100">نشط</span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-50 text-rose-600 border border-rose-100">غير نشط</span>
                            @endif
                        </td>
                        <td class="py-4 px-6 text-center space-x-2 space-x-reverse">
                            <!-- زر التعديل -->
                            <button onclick="openModal('editModal{{ $org->id }}')" 
                                class="px-3 py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-700 rounded-xl text-xs font-semibold transition-all">
                                تعديل
                            </button>

                            <!-- زر الحذف -->
                            @if (\App\Support\Tenancy::isSuperAdmin())
                            <form action="{{ route('organizations.destroy', $org->id) }}" method="POST" class="inline-block">
                                @csrf
                                @method('DELETE')
                                <button type="submit" onclick="return confirm('هل أنت متأكد من حذف هذه الصيدلية؟')" 
                                    class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-xl text-xs font-semibold transition-all">
                                    حذف
                                </button>
                            </form>
                            @endif
                        </td>
                    </tr>

                    <!-- مودال التعديل لكل صف -->
                    <div id="editModal{{ $org->id }}" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
                        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in duration-150">
                            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                                <h3 class="font-bold text-slate-800 text-sm">تعديل الصيدلية: {{ $org->name }}</h3>
                                <button onclick="closeModal('editModal{{ $org->id }}')" class="text-slate-400 hover:text-slate-600 font-bold">&times;</button>
                            </div>
                            <form action="{{ route('organizations.update', $org->id) }}" method="POST">
                                @csrf
                                @method('PUT')
                                <div class="p-6 space-y-4">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 mb-1">اسم الصيدلية</label>
                                        <input type="text" name="name" value="{{ $org->name }}" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 mb-1">العملة</label>
                                        <input type="text" name="currency" value="{{ $org->currency }}" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 mb-1">المنطقة الزمنية</label>
                                        <input type="text" name="timezone" value="{{ $org->timezone }}" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 mb-1">الحالة</label>
                                        <select name="status" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                                            <option value="active" {{ $org->status == 'active' ? 'selected' : '' }}>نشط</option>
                                            <option value="inactive" {{ $org->status == 'inactive' ? 'selected' : '' }}>غير نشط</option>
                                        </select>
                                    </div>
                                    <label class="flex items-start gap-2 text-xs text-slate-700 cursor-pointer">
                                        <input type="hidden" name="require_shift" value="0">
                                        <input type="checkbox" name="require_shift" value="1" class="mt-0.5 rounded border-slate-300" {{ $org->require_shift ? 'checked' : '' }}>
                                        <span><b>إلزام فتح وردية قبل البيع</b><br><span class="text-slate-500">لا يستطيع الموظف البيع من نقطة البيع إلا إذا فتح وردية على خزينة، فيُحاسَب كل كاشير على صندوقه.</span></span>
                                    </label>
                                </div>
                                <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-2">
                                    <button type="button" onclick="closeModal('editModal{{ $org->id }}')" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-xl text-xs font-semibold">إلغاء</button>
                                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold">حفظ التغييرات</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-8 text-slate-400 text-xs">لا توجد منظمات مضافة حتى الآن.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
<div class="p-4">{{ $organizations->links() }}</div>
        </div>
    </div>
</div>

<!-- مودال الإنشاء (Create Modal) -->
<div id="createModal" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
            <h3 class="font-bold text-slate-800 text-sm">إضافة صيدلية جديدة</h3>
            <button onclick="closeModal('createModal')" class="text-slate-400 hover:text-slate-600 font-bold">&times;</button>
        </div>
        <form action="{{ route('organizations.store') }}" method="POST">
            @csrf
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">اسم الصيدلية</label>
                    <input type="text" name="name" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none" placeholder="أدخل اسم الصيدلية">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">العملة</label>
                    <input type="text" name="currency" value="USD" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">المنطقة الزمنية</label>
                    <input type="text" name="timezone" value="UTC" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">الحالة</label>
                    <select name="status" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <option value="active">نشط</option>
                        <option value="inactive">غير نشط</option>
                    </select>
                </div>
            </div>
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-2">
                <button type="button" onclick="closeModal('createModal')" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-xl text-xs font-semibold">إلغاء</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold">حفظ الصيدلية</button>
            </div>
        </form>
    </div>
</div>

<!-- سكربت التحكم بفتح وإغلاق النوافذ المنبثقة -->
<script>
    function openModal(modalId) {
        document.getElementById(modalId).classList.remove('hidden');
    }
    function closeModal(modalId) {
        document.getElementById(modalId).classList.add('hidden');
    }
</script>
@endsection