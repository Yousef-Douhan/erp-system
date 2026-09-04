@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto space-y-6 p-4">
    <div class="flex justify-between items-center bg-white p-4 rounded-xl border shadow-sm">
        <h2 class="text-lg font-bold text-slate-800">إدارة طرق الدفع</h2>
        <button onclick="openModal('addMethodModal')" class="px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-lg">إضافة طريقة دفع جديدة</button>
    </div>

    <!-- جدول عرض الطرق -->
    <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
        <table class="w-full text-right text-xs">
            <thead class="bg-slate-50 border-b text-slate-700 font-bold">
                <tr>
                    <th class="p-3">الاسم</th>
                    <th class="p-3">الكود البرمجي</th>
                    <th class="p-3">الأيقونة</th>
                    <th class="p-3">اللون</th>
                    <th class="p-3">تتطلب محفظة؟</th>
                    <th class="p-3">الحالة</th>
                    <th class="p-3 text-center">الإجراءات</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach($methods as $method)
                <tr>
                    <td class="p-3 font-bold text-slate-800">{{ $method->name }}</td>
                    <td class="p-3 text-slate-500 font-mono">{{ $method->code }}</td>
                    <td class="p-3 text-lg">{{ $method->icon }}</td>
                    <td class="p-3"><span class="px-2 py-1 bg-{{ $method->color }}-100 text-{{ $method->color }}-700 rounded font-bold">{{ $method->color }}</span></td>
                    <td class="p-3">{{ $method->requires_wallet ? 'نعم' : 'لا' }}</td>
                    <td class="p-3">
                        <span class="px-2 py-1 rounded text-white text-[10px] {{ $method->is_active ? 'bg-emerald-600' : 'bg-rose-500' }}">
                            {{ $method->is_active ? 'نشط' : 'معطل' }}
                        </span>
                    </td>
                    <td class="p-3 text-center flex justify-center gap-2">
                        <form action="{{ route('payment-methods.destroy', $method->id) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
                            @csrf @method('DELETE')
                            <button type="submit" class="px-2.5 py-1 bg-rose-100 text-rose-600 rounded font-bold">حذف</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<!-- Modal إضافة طريقة دفع -->
<div id="addMethodModal" class="fixed inset-0 bg-slate-900/50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-xl p-5 w-full max-w-md space-y-4 shadow-xl">
        <h3 class="text-sm font-bold text-slate-800">إضافة طريقة دفع جديدة</h3>
        <form action="{{ route('payment-methods.store') }}" method="POST" class="space-y-3">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">اسم طريقة الدفع</label>
                <input type="text" name="name" placeholder="مثال: بطاقة مدى" class="w-full rounded-lg border-slate-200 text-xs" required>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">الكود البرمجي (بالإنجليزية بدون مسافات)</label>
                <input type="text" name="code" placeholder="مثال: mada_card" class="w-full rounded-lg border-slate-200 text-xs font-mono" required>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">الأيقونة (إيموجي)</label>
                    <input type="text" name="icon" placeholder="💳" class="w-full rounded-lg border-slate-200 text-xs">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">اللون (Tailwind Color)</label>
                    <select name="color" class="w-full rounded-lg border-slate-200 text-xs">
                        <option value="emerald">أخضر (emerald)</option>
                        <option value="cyan">سماوي (cyan)</option>
                        <option value="orange">برتقالي (orange)</option>
                        <option value="purple">بنفسجي (purple)</option>
                        <option value="blue">أزرق (blue)</option>
                    </select>
                </div>
            </div>
            <div class="flex items-center gap-4 pt-2">
                <label class="flex items-center gap-1.5 text-xs font-semibold text-slate-700 cursor-pointer">
                    <input type="checkbox" name="requires_wallet" value="1" class="rounded border-slate-300 text-emerald-600">
                    تتطلب رقم محفظة/حساب؟
                </label>
                <label class="flex items-center gap-1.5 text-xs font-semibold text-slate-700 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300 text-emerald-600">
                    نشط وتظهر في نقطة البيع
                </label>
            </div>
            <div class="flex justify-end gap-2 pt-3">
                <button type="button" onclick="closeModal('addMethodModal')" class="px-3 py-1.5 bg-slate-100 text-slate-600 text-xs rounded-lg">إلغاء</button>
                <button type="submit" class="px-3 py-1.5 bg-emerald-600 text-white text-xs rounded-lg font-bold">حفظ</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
</script>
@endsection