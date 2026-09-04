@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 h-fit">
        <h3 id="form-title" class="text-base font-black text-slate-800 mb-4">إضافة خزينة جديدة</h3>

        {{-- عرض أخطاء التحقق إن وجدت --}}
        @if ($errors->any())
            <div class="mb-4 p-3 bg-rose-50 text-rose-600 rounded-xl text-xs">
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- رسالة النجاح إن وجدت --}}
        @if (session('success'))
            <div class="mb-4 p-3 bg-emerald-50 text-emerald-600 rounded-xl text-xs font-bold">
                {{ session('success') }}
            </div>
        @endif

        <form id="crud-form" action="{{ route('cash-registers.store') }}" method="POST" class="space-y-4">
            @csrf
            <input type="hidden" id="form-method" name="_method" value="POST">
            
            {{-- قائمة اختيار الفرع --}}
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">الفرع</label>
                <select id="branch_id" name="branch_id" required class="w-full px-4 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 outline-none bg-white">
                    <option value="">-- اختر الفرع --</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">اسم الخزينة</label>
                <input type="text" id="name" name="name" required class="w-full px-4 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 outline-none">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">الرصيد الافتتاحي</label>
                <input type="number" step="0.01" id="opening_balance" name="opening_balance" required value="0.00" class="w-full px-4 py-2 rounded-xl border border-slate-200 text-xs font-mono focus:ring-2 focus:ring-indigo-500 outline-none">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">حالة الخزينة</label>
                <select id="status" name="status" required class="w-full px-4 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-indigo-500 outline-none bg-white">
                    <option value="active">نشطة (Active)</option>
                    <option value="inactive">موقوفة (Inactive)</option>
                </select>
            </div>
            <div class="flex items-center gap-2 pt-2">
                <button type="submit" id="submit-btn" class="w-full py-2.5 bg-indigo-600 text-white rounded-xl text-xs font-bold hover:bg-indigo-700 shadow-md transition">حفظ الخزينة</button>
                <button type="button" id="cancel-btn" onclick="resetForm()" class="hidden w-full py-2.5 bg-slate-100 text-slate-600 rounded-xl text-xs font-bold hover:bg-slate-200 transition">إلغاء</button>
            </div>
        </form>
    </div>

    <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h3 class="text-base font-black text-slate-800">إدارة الخزائن والصناديق</h3>
            <span class="text-xs text-slate-400 font-semibold">الإجمالي: {{ $registers->count() }}</span>
        </div>
        <table class="w-full text-right border-collapse text-xs">
            <thead>
                <tr class="bg-slate-50 text-slate-500 border-b border-slate-200">
                    <th class="p-4 font-bold">اسم الخزينة</th>
                    <th class="p-4 font-bold">الفرع</th>
                    <th class="p-4 font-bold">الرصيد الحالي</th>
                    <th class="p-4 font-bold">الحالة</th>
                    <th class="p-4 font-bold text-center">التحكم</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($registers as $register)
                <tr class="hover:bg-slate-50/50">
                    {{-- اسم الخزينة الصحيح --}}
                    <td class="p-4 font-bold text-slate-800">{{ $register->name }}</td>
                    
                    {{-- اسم الفرع الصحيح --}}
                    <td class="p-4 text-slate-600">{{ $register->branch->name ?? '---' }}</td>
                    
                    <td class="p-4 font-mono font-bold text-indigo-600">{{ number_format($register->current_balance, 2) }} ر.س</td>
                    <td class="p-4">
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold {{ $register->status == 'active' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600' }}">
                            {{ $register->status }}
                        </span>
                    </td>
                    <td class="p-4 text-center space-x-2 space-x-reverse">
                        <button type="button" onclick="editItem('{{ $register->id }}', '{{ $register->branch_id }}', '{{ $register->name }}', '{{ $register->opening_balance }}', '{{ $register->status }}')" class="text-indigo-600 hover:underline font-bold">تعديل</button>
                        <form action="{{ route('cash-registers.destroy', $register->id) }}" method="POST" class="inline-block" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-rose-600 hover:underline font-bold">حذف</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="p-6 text-center text-slate-400">لا توجد خزائن مضافة.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
    function editItem(id, branchId, name, openingBalance, status) {
        const form = document.getElementById('crud-form');
        form.action = `/cash-registers/${id}`;
        document.getElementById('form-method').value = 'PUT';
        document.getElementById('branch_id').value = branchId;
        document.getElementById('name').value = name;
        document.getElementById('opening_balance').value = openingBalance;
        document.getElementById('status').value = status;
        document.getElementById('form-title').innerText = 'تعديل الخزينة';
        
        const btn = document.getElementById('submit-btn');
        btn.innerText = 'تحديث البيانات';
        btn.className = 'w-full py-2.5 bg-emerald-600 text-white rounded-xl text-xs font-bold hover:bg-emerald-700 shadow-md transition';
        document.getElementById('cancel-btn').classList.remove('hidden');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function resetForm() {
        const form = document.getElementById('crud-form');
        form.action = "{{ route('cash-registers.store') }}";
        document.getElementById('form-method').value = 'POST';
        form.reset();
        document.getElementById('form-title').innerText = 'إضافة خزينة جديدة';
        
        const btn = document.getElementById('submit-btn');
        btn.innerText = 'حفظ الخزينة';
        btn.className = 'w-full py-2.5 bg-indigo-600 text-white rounded-xl text-xs font-bold hover:bg-indigo-700 shadow-md transition';
        document.getElementById('cancel-btn').classList.add('hidden');
    }
</script>
@endsection