<x-app-layout>
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <!-- رأس الصفحة مع زر الإضافة -->
            <div class="flex items-center justify-between mb-6 bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                <div>
                    <h2 class="text-xl font-bold text-slate-800">إدارة الأدوار والصلاحيات</h2>
                    <p class="text-xs text-slate-400 mt-0.5">عرض وإدارة الأدوار والصلاحيات الممنوحة للمستخدمين</p>
                </div>

                <a href="{{ route('roles.create') }}" class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl text-sm font-semibold shadow-md shadow-indigo-100 transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    <span>إضافة دور جديد</span>
                </a>
            </div>

            <!-- جدول عرض الأدوار والصلاحيات -->
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-right border-collapse">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/50 text-slate-500 text-xs font-bold uppercase tracking-wider">
                                <th class="py-4 px-6">اسم الدور</th>
                                <th class="py-4 px-6">الصلاحيات الممنوحة</th>
                                <th class="py-4 px-6 text-center">العمليات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            @foreach($roles as $role)
                                <tr class="hover:bg-slate-50/50 transition-all">
                                    <td class="py-4 px-6 font-bold text-slate-800 flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-indigo-600"></span>
                                        {{ $role->name }}
                                    </td>
                                    <td class="py-4 px-6">
                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach($role->permissions as $permission)
                                                <span class="px-2.5 py-1 bg-indigo-50/60 border border-indigo-100 text-indigo-600 rounded-lg text-xs font-medium">
                                                    {{ $permission->name }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="py-4 px-6 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <a href="{{ route('roles.edit', $role->id) }}" class="px-3 py-1.5 bg-indigo-50 text-indigo-600 hover:bg-indigo-100 rounded-lg text-xs font-semibold transition-all">
                                                تعديل
                                            </a>
                                            <form action="{{ route('roles.destroy', $role->id) }}" method="POST" class="inline" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="px-3 py-1.5 bg-rose-50 text-rose-600 hover:bg-rose-100 rounded-lg text-xs font-semibold transition-all">
                                                    حذف
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>