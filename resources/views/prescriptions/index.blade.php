@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 h-fit">
        <h3 id="form-title" class="text-base font-black text-slate-800 mb-4">إضافة روشتة جديدة</h3>

        @if ($errors->any())
            <div class="mb-4 p-3 bg-rose-50 text-rose-600 rounded-xl text-xs">
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (session('success'))
            <div class="mb-4 p-3 bg-emerald-50 text-emerald-600 rounded-xl text-xs font-bold">
                {{ session('success') }}
            </div>
        @endif

        <form id="crud-form" action="{{ route('prescriptions.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <input type="hidden" id="form-method" name="_method" value="POST">

            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">العميل (المريض)</label>
                <select id="customer_id" name="customer_id" required class="w-full px-4 py-2 rounded-xl border border-slate-200 text-xs bg-white">
                    <option value="">-- اختر العميل --</option>
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}">{{ $customer->name ?? $customer->phone }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">اسم الطبيب</label>
                <input type="text" id="doctor_name" name="doctor_name" required class="w-full px-4 py-2 rounded-xl border border-slate-200 text-xs">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">التشخيص</label>
                <textarea id="diagnosis" name="diagnosis" rows="2" class="w-full px-4 py-2 rounded-xl border border-slate-200 text-xs"></textarea>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">صورة الروشتة</label>
                <input type="file" name="image" class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-600 hover:file:bg-indigo-100">
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" id="is_filled" name="is_filled" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                <label for="is_filled" class="text-xs font-bold text-slate-600">تم صرف الروشتة</label>
            </div>

            <div class="flex items-center gap-2 pt-2">
                <button type="submit" id="submit-btn" class="w-full py-2.5 bg-indigo-600 text-white rounded-xl text-xs font-bold hover:bg-indigo-700 transition">حفظ الروشتة</button>
                <button type="button" id="cancel-btn" onclick="resetForm()" class="hidden w-full py-2.5 bg-slate-100 text-slate-600 rounded-xl text-xs font-bold transition">إلغاء</button>
            </div>
        </form>
    </div>

    <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h3 class="text-base font-black text-slate-800">إدارة الروشيتات الطبية</h3>
            <span class="text-xs text-slate-400 font-semibold">الإجمالي: {{ $prescriptions->count() }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-right border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 border-b border-slate-200">
                        <th class="p-4 font-bold">الطبيب</th>
                        <th class="p-4 font-bold">العميل</th>
                        <th class="p-4 font-bold">صورة الروشتة</th>
                        <th class="p-4 font-bold">الحالة</th>
                        <th class="p-4 font-bold text-center">التحكم</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($prescriptions as $prescription)
                    <tr class="hover:bg-slate-50/50">
                        <td class="p-4 font-bold text-slate-800">{{ $prescription->doctor_name }}</td>
                        <td class="p-4 text-slate-600">{{ $prescription->customer->name ?? '---' }}</td>
                        
                        <!-- عمود عرض ملف / صورة الروشتة (تم استخدام image_path) -->
                        <td class="p-4">
                            @if($prescription->image_path)
                                <a href="{{ asset('storage/' . $prescription->image_path) }}" target="_blank" 
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-indigo-50 text-indigo-600 hover:bg-indigo-100 font-bold transition-all shadow-sm">
                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    عرض الملف
                                </a>
                            @else
                                <span class="text-slate-400 italic">لا يوجد ملف</span>
                            @endif
                        </td>

                        <td class="p-4">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold {{ $prescription->is_filled ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600' }}">
                                {{ $prescription->is_filled ? 'تم الصرف' : 'معلقة' }}
                            </span>
                        </td>
                        <td class="p-4 text-center space-x-2 space-x-reverse">
                            <button type="button" onclick="editItem('{{ $prescription->id }}', '{{ $prescription->customer_id }}', '{{ $prescription->doctor_name }}', '{{ $prescription->diagnosis }}', '{{ $prescription->is_filled }}')" class="text-indigo-600 hover:underline font-bold">تعديل</button>
                            <form action="{{ route('prescriptions.destroy', $prescription->id) }}" method="POST" class="inline-block" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-rose-600 hover:underline font-bold">حذف</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="p-6 text-center text-slate-400">لا توجد روشيتات مسجلة.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function editItem(id, customerId, doctorName, diagnosis, isFilled) {
        const form = document.getElementById('crud-form');
        form.action = `/prescriptions/${id}`;
        document.getElementById('form-method').value = 'PUT';
        document.getElementById('customer_id').value = customerId;
        document.getElementById('doctor_name').value = doctorName;
        document.getElementById('diagnosis').value = diagnosis;
        document.getElementById('is_filled').checked = isFilled == '1';
        document.getElementById('form-title').innerText = 'تعديل الروشتة';
        
        const btn = document.getElementById('submit-btn');
        btn.innerText = 'تحديث البيانات';
        btn.className = 'w-full py-2.5 bg-emerald-600 text-white rounded-xl text-xs font-bold hover:bg-emerald-700 transition';
        document.getElementById('cancel-btn').classList.remove('hidden');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function resetForm() {
        const form = document.getElementById('crud-form');
        form.action = "{{ route('prescriptions.store') }}";
        document.getElementById('form-method').value = 'POST';
        form.reset();
        document.getElementById('form-title').innerText = 'إضافة روشتة جديدة';
        
        const btn = document.getElementById('submit-btn');
        btn.innerText = 'حفظ الروشتة';
        btn.className = 'w-full py-2.5 bg-indigo-600 text-white rounded-xl text-xs font-bold hover:bg-indigo-700 transition';
        document.getElementById('cancel-btn').classList.add('hidden');
    }
</script>
@endsection