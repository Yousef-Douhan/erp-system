<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4" dir="rtl">
            <div>
                <h2 class="font-extrabold text-2xl text-slate-800 tracking-tight flex items-center gap-3">
                    <span class="p-2 bg-indigo-50 text-indigo-600 rounded-xl shadow-sm">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </span>
                    إنشاء دور جديد
                </h2>
                <p class="text-xs text-slate-500 mt-1 mr-11">تحديد اسم الدور وتعيين الصلاحيات المخصصة له</p>
            </div>
            
            <a href="{{ route('roles.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-100 text-slate-700 font-medium text-sm rounded-xl hover:bg-slate-200 transition-all duration-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                </svg>
                العودة للقائمة
            </a>
        </div>
    </x-slot>

    <div class="py-10 bg-slate-50/50 min-h-screen" dir="rtl">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6 md:p-8">
                <form action="{{ route('roles.store') }}" method="POST">
                    @csrf
                    
                    <!-- Role Name Field -->
                    <div class="mb-8">
                        <label for="name" class="block text-sm font-bold text-slate-700 mb-2">اسم الدور (Role Name)</label>
                        <input type="text" name="name" id="name" placeholder="مثال: Supervisor, Accountant..." class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition-all outline-none text-slate-800 text-sm" required>
                    </div>

                    <!-- Permissions Selection Grid -->
                    <div class="mb-8">
                        <label class="block text-sm font-bold text-slate-700 mb-3">تحديد الصلاحيات المتاحة:</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                            @foreach($permissions as $permission)
                                <label class="flex items-center gap-3 p-3.5 rounded-xl border border-slate-100 bg-slate-50/50 hover:bg-indigo-50/40 hover:border-indigo-200 transition-all cursor-pointer group">
                                    <input type="checkbox" name="permissions[]" value="{{ $permission->name }}" class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 transition-all">
                                    <span class="text-xs font-semibold text-slate-700 group-hover:text-indigo-900">{{ $permission->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-4 border-t border-slate-100 flex justify-end">
                        <button type="submit" class="px-6 py-3 bg-gradient-to-r from-indigo-600 to-violet-600 text-white font-medium text-sm rounded-xl shadow-md shadow-indigo-200 hover:shadow-lg hover:from-indigo-700 hover:to-violet-700 transition-all">
                            حفظ الدور الجديد
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>