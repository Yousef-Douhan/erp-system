@extends('layouts.app')

@section('content')
    <div class="max-w-3xl mx-auto space-y-6" dir="rtl">
        <div class="flex items-center justify-between bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
            <div>
                <h2 class="font-extrabold text-xl text-slate-800 tracking-tight">تعديل بيانات المستخدم: {{ $user->username }}</h2>
            </div>
            <a href="{{ route('users.index') }}" class="px-4 py-2 bg-slate-100 text-slate-600 font-bold text-xs rounded-xl hover:bg-slate-200 transition-all">العودة للقائمة</a>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <form action="{{ route('users.update', $user->id) }}" method="POST" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">البريد الإلكتروني</label>
                    <input type="email" name="email" value="{{ $user->email }}" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition-all outline-none text-slate-800 text-sm" dir="ltr">
                </div>

                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">كلمة المرور الجديدة (اتركها فارغة إذا لم تُرِد تغييرها)</label>
                    <input type="password" name="password" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition-all outline-none text-slate-800 text-sm" dir="ltr">
                </div>

                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">الدَور المخصص</label>
                    <select name="role" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition-all outline-none text-slate-800 text-sm" required>
                        @foreach($roles as $role)
                            <option value="{{ $role->name }}" {{ $user->hasRole($role->name) ? 'selected' : '' }}>
                                {{ $role->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="pt-6 mt-6 border-t border-slate-100 flex justify-end">
                    <button type="submit" class="px-6 py-3 bg-gradient-to-r from-indigo-600 to-violet-600 text-white font-medium text-sm rounded-xl shadow-md hover:shadow-lg transition-all">
                        تحديث التغييرات
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection