@extends('layouts.app')

@section('content')
    <div class="space-y-6" dir="rtl">
        
        <!-- رأس الصفحة -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
            <div>
                <h2 class="font-extrabold text-xl text-slate-800 tracking-tight flex items-center gap-3">
                    <span class="p-2 bg-indigo-50 text-indigo-600 rounded-xl shadow-sm">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </span>
                    إدارة المستخدمين
                </h2>
                <p class="text-xs text-slate-400 mt-1 mr-11">قائمة بجميع مستخدمي النظام وصلاحيات الوصول</p>
            </div>
            
            <button onclick="toggleModal('addUserModal')" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 text-white font-bold text-xs rounded-xl hover:bg-indigo-700 shadow-sm shadow-indigo-200 transition-all cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                إضافة مستخدم جديد
            </button>
        </div>

        <!-- رسائل النجاح والأخطاء -->
        @if(session('success'))
            <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-bold flex items-center gap-2">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold space-y-1">
                <p>يرجى تصحيح الأخطاء التالية:</p>
                <ul class="list-disc list-inside space-y-0.5 font-normal">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- جدول المستخدمين -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-right border-collapse">
                    <thead>
                        <tr class="bg-slate-50/75 text-slate-400 text-[11px] font-extrabold uppercase tracking-wider border-b border-slate-100">
                            <th class="px-6 py-4">اسم المستخدم</th>
                            <th class="px-6 py-4">البريد الإلكتروني</th>
                            <th class="px-6 py-4">الصيدلية / الفرع</th>
                            <th class="px-6 py-4">الدور / الصلاحية</th>
                            <th class="px-6 py-4">الحالة</th>
                            <th class="px-6 py-4 text-left">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs font-medium text-slate-600">
                        @forelse($users as $user)
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-6 py-4 font-bold text-slate-800 flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-indigo-50 text-indigo-600 font-bold flex items-center justify-center text-xs">
                                        {{ mb_substr($user->username ?? $user->name, 0, 2) }}
                                    </div>
                                    <div>{{ $user->username ?? $user->name }}@if ($user->full_name)<div class="text-[11px] font-normal text-slate-400">{{ $user->full_name }}</div>@endif</div>
                                </td>
                                <td class="px-6 py-4 font-mono text-slate-500" dir="ltr">{{ $user->email }}</td>
                                <td class="px-6 py-4">
                                    {{ $user->organization->name ?? 'مدير النظام' }}
                                    <div class="text-[11px] text-slate-500">{{ $user->branch->name ?? ($user->organization ? 'كل الفروع' : '') }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    @foreach($user->getRoleNames() as $role)
                                        <span class="px-2.5 py-1 bg-indigo-50 text-indigo-700 rounded-lg text-[11px] font-bold">{{ $role }}</span>
                                    @endforeach
                                </td>
                                <td class="px-6 py-4">
                                    @if($user->is_active)
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 font-bold text-[11px]">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> نشط
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-rose-50 text-rose-700 font-bold text-[11px]">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> معطل
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-left">
                                    <div class="flex items-center justify-end gap-1.5">
                                        @can('update', $user)
                                        <!-- زر التعديل عبر النافذة المنبثقة -->
                                        <button type="button" 
                                            onclick="openEditModal({{ json_encode($user) }}, '{{ $user->getRoleNames()->first() }}')" 
                                            class="p-1.5 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-100 transition-colors" title="تعديل">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        @endcan

                                        @can('delete', $user)
                                        <!-- زر الحذف -->
                                        <form action="{{ route('users.destroy', $user->id) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1.5 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 transition-colors" title="حذف">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-12 text-center text-slate-400 font-medium">لا توجد بيانات مستخدمين مضافة حتى الآن.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
<div class="p-4">{{ $users->links() }}</div>
            </div>
        </div>
    </div>

    <!-- نافذة إضافة مستخدم جديد -->
    <div id="addUserModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm hidden">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-100 w-full max-w-lg mx-4 overflow-hidden" dir="rtl">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 bg-slate-50/50">
                <h3 class="font-extrabold text-sm text-slate-800">إضافة مستخدم جديد للنظام</h3>
                <button type="button" onclick="toggleModal('addUserModal')" class="p-1 rounded-lg text-slate-400 hover:bg-slate-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form action="{{ route('users.store') }}" method="POST" class="p-6 space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">اسم المستخدم</label>
                    <input type="text" name="username" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">البريد الإلكتروني</label>
                    <input type="email" name="email" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none" dir="ltr">
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">الاسم الكامل</label>
                        <input type="text" name="full_name" maxlength="255" placeholder="مثال: أحمد خليل" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">الراتب الشهري (₪)</label>
                        <input type="number" name="salary" step="0.01" min="0" placeholder="0.00" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none" dir="ltr">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">رقم الهاتف</label>
                        <input type="text" name="phone" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none" dir="ltr">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">كلمة المرور</label>
                        <input type="password" name="password" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none" dir="ltr">
                        <input type="password" name="password_confirmation" required placeholder="أعد كتابة كلمة المرور" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none mt-2" dir="ltr" autocomplete="new-password">
                        <p class="text-[11px] text-slate-500 mt-1">8 أحرف على الأقل، وتحتوي حروفاً وأرقاماً.</p>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @include('users._scope', ['idPrefix' => 'add_'])
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">الدور / الصلاحية</label>
                        <select name="role" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                            <option value="">اختر الدور...</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->name }}">{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="flex items-center gap-2 pt-1">
                    <input type="checkbox" name="is_active" id="add_is_active" value="1" checked class="w-4 h-4 rounded border-slate-300 text-indigo-600">
                    <label for="add_is_active" class="text-xs font-bold text-slate-700 cursor-pointer">حساب نشط ومفعل</label>
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                    <button type="button" onclick="toggleModal('addUserModal')" class="px-4 py-2 rounded-xl bg-slate-100 text-slate-600 font-bold text-xs">إلغاء</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 text-white font-bold text-xs shadow-sm shadow-indigo-200 hover:bg-indigo-700">حفظ المستخدم</button>
                </div>
            </form>
        </div>
    </div>

    <!-- نافذة تعديل المستخدم (تفتح في نفس الصفحة) -->
    <div id="editUserModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm hidden">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-100 w-full max-w-lg mx-4 overflow-hidden" dir="rtl">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 bg-slate-50/50">
                <h3 class="font-extrabold text-sm text-slate-800">تعديل بيانات المستخدم</h3>
                <button type="button" onclick="toggleModal('editUserModal')" class="p-1 rounded-lg text-slate-400 hover:bg-slate-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form id="editUserForm" method="POST" class="p-6 space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">اسم المستخدم</label>
                    <input type="text" id="edit_username" name="username" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">البريد الإلكتروني</label>
                    <input type="email" id="edit_email" name="email" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none" dir="ltr">
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">الاسم الكامل</label>
                        <input type="text" id="edit_full_name" name="full_name" maxlength="255" placeholder="مثال: أحمد خليل" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">الراتب الشهري (₪)</label>
                        <input type="number" id="edit_salary" name="salary" step="0.01" min="0" placeholder="0.00" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none" dir="ltr">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">رقم الهاتف</label>
                        <input type="text" id="edit_phone" name="phone" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none" dir="ltr">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">كلمة المرور الجديدة (اختياري)</label>
                        <input type="password" name="password" placeholder="اتركها فارغة إن لم ترد تغييرها" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none" dir="ltr">
                        <input type="password" name="password_confirmation" placeholder="أعد كتابة كلمة المرور" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none mt-2" dir="ltr" autocomplete="new-password">
                        <p class="text-[11px] text-slate-500 mt-1">8 أحرف على الأقل، وتحتوي حروفاً وأرقاماً.</p>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @include('users._scope', ['idPrefix' => 'edit_'])
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">الدور / الصلاحية</label>
                        <select id="edit_role" name="role" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                            <option value="">اختر الدور...</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->name }}">{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="flex items-center gap-2 pt-1">
                    <input type="checkbox" name="is_active" id="edit_is_active" value="1" class="w-4 h-4 rounded border-slate-300 text-indigo-600">
                    <label for="edit_is_active" class="text-xs font-bold text-slate-700 cursor-pointer">حساب نشط ومفعل</label>
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                    <button type="button" onclick="toggleModal('editUserModal')" class="px-4 py-2 rounded-xl bg-slate-100 text-slate-600 font-bold text-xs">إلغاء</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 text-white font-bold text-xs shadow-sm shadow-indigo-200 hover:bg-indigo-700">تحديث التغييرات</button>
                </div>
            </form>
        </div>
    </div>

    <!-- أكواد الـ JavaScript للتحكم بالنوافذ المنبثقة وتعبئة بيانات التعديل -->
    <script>
        function toggleModal(modalId) {
            document.getElementById(modalId).classList.toggle('hidden');
        }

        function openEditModal(user, userRole) {
            // تحديد مسار الفورم لتحديث المستخدم المختار
            const form = document.getElementById('editUserForm');
            form.action = `{{ url('users') }}/${user.id}`;

            // تعبئة الحقول بالبيانات الحالية
            document.getElementById('edit_username').value = user.username || user.name || '';
            document.getElementById('edit_email').value = user.email || '';
            document.getElementById('edit_phone').value = user.phone || '';
            document.getElementById('edit_full_name').value = user.full_name || '';
            document.getElementById('edit_salary').value = user.salary ?? '';
            const orgSel = document.getElementById('edit_organization_id');
            if (orgSel) { orgSel.value = user.organization_id || ''; orgSel.dispatchEvent(new Event('change')); }
            const branchSel = document.getElementById('edit_branch_id');
            if (branchSel) branchSel.value = user.branch_id || '';
            document.getElementById('edit_role').value = userRole || '';
            document.getElementById('edit_is_active').checked = user.is_active == 1;

            // فتح نافذة التعديل
            toggleModal('editUserModal');
        }
    </script>
@endsection