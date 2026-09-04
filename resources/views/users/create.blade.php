<x-app-layout>
    <x-slot name="header">
        <h2 class="font-extrabold text-xl text-slate-800">إضافة مستخدم جديد</h2>
    </x-slot>

    <div class="max-w-3xl mx-auto bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
        <form action="{{ route('users.store') }}" method="POST" class="space-y-4" dir="rtl">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">اسم المستخدم (Username)</label>
                    <input type="text" name="username" value="{{ old('username') }}" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">البريد الإلكتروني</label>
                    <input type="email" name="email" value="{{ old('email') }}" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">رقم الهاتف</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">كلمة المرور</label>
                    <input type="password" name="password" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">المنظمة التابع لها</label>
                    <select name="organization_id" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <option value="">بدون منظمة (عام)</option>
                        @foreach($organizations as $org)
                            <option value="{{ $org->id }}">{{ $org->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">الدور والصلاحية</label>
                    <select name="role" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <option value="">اختر الدور...</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->name }}">{{ $role->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex items-center gap-2 pt-2">
                <input type="checkbox" name="is_active" id="is_active" value="1" checked class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                <label for="is_active" class="text-xs font-bold text-slate-700">حساب نشط</label>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                <a href="{{ route('users.index') }}" class="px-4 py-2 rounded-xl bg-slate-100 text-slate-600 font-bold text-xs">إلغاء</a>
                <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 text-white font-bold text-xs shadow-sm">حفظ المستخدم</button>
            </div>
        </form>
    </div>
</x-app-layout>