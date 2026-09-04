@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-6">
    
    <!-- فورم الإضافة والتعديل -->
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 h-fit">
        <h3 id="form-title" class="text-base font-black text-slate-800 mb-4">إضافة تنبيه دوائي جديد</h3>

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

        <form id="crud-form" action="{{ route('drug-alerts.store') }}" method="POST" class="space-y-4">
            @csrf
            <input type="hidden" id="form-method" name="_method" value="POST">

            <!-- اختيار الدواء / المنتج -->
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">الدواء / المنتج</label>
                <select id="product_id" name="product_id" required class="w-full px-4 py-2 rounded-xl border border-slate-200 text-xs bg-white">
                    <option value="">-- اختر الدواء --</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}">{{ $product->name ?? $product->title }}</option>
                    @endforeach
                </select>
            </div>

            <!-- مستوى الخطورة -->
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">مستوى الخطورة</label>
                <select id="severity" name="severity" required class="w-full px-4 py-2 rounded-xl border border-slate-200 text-xs bg-white">
                    <option value="low">منخفض (Low)</option>
                    <option value="medium" selected>متوسط (Medium)</option>
                    <option value="high">مرتفع (High)</option>
                    <option value="critical">حرج (Critical)</option>
                </select>
            </div>

            <!-- رسالة التنبيه -->
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">نص التنبيه</label>
                <textarea id="message" name="message" rows="3" required placeholder="اكتب تفاصيل التنبيه أو التعارض الدوائي هنا..." class="w-full px-4 py-2 rounded-xl border border-slate-200 text-xs"></textarea>
            </div>

            <div class="flex items-center gap-2 pt-2">
                <button type="submit" id="submit-btn" class="w-full py-2.5 bg-indigo-600 text-white rounded-xl text-xs font-bold hover:bg-indigo-700 transition">حفظ التنبيه</button>
                <button type="button" id="cancel-btn" onclick="resetForm()" class="hidden w-full py-2.5 bg-slate-100 text-slate-600 rounded-xl text-xs font-bold transition">إلغاء</button>
            </div>
        </form>
    </div>

    <!-- جدول عرض التنبيهات -->
    <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h3 class="text-base font-black text-slate-800">إدارة التنبيهات الدوائية</h3>
            <span class="text-xs text-slate-400 font-semibold">الإجمالي: {{ $alerts->count() }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-right border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 border-b border-slate-200">
                        <th class="p-4 font-bold">الدواء</th>
                        <th class="p-4 font-bold">مستوى الخطورة</th>
                        <th class="p-4 font-bold">الرسالة</th>
                        <th class="p-4 font-bold text-center">التحكم</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($alerts as $alert)
                    <tr class="hover:bg-slate-50/50">
                        <td class="p-4 font-bold text-slate-800">{{ $alert->product->name ?? $alert->product->title ?? '---' }}</td>
                        
                        <!-- تصميم شارة الخطورة بالألوان -->
                        <td class="p-4">
                            @php
                                $badgeClass = match($alert->severity) {
                                    'critical' => 'bg-rose-50 text-rose-600 border border-rose-200',
                                    'high'     => 'bg-orange-50 text-orange-600 border border-orange-200',
                                    'medium'   => 'bg-amber-50 text-amber-600 border border-amber-200',
                                    default    => 'bg-blue-50 text-blue-600 border border-blue-200',
                                };
                            @endphp
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase {{ $badgeClass }}">
                                {{ $alert->severity }}
                            </span>
                        </td>

                        <td class="p-4 text-slate-600 max-w-xs truncate" title="{{ $alert->message }}">{{ $alert->message }}</td>

                        <td class="p-4 text-center space-x-2 space-x-reverse">
                            <button type="button" 
                                onclick="editItem('{{ $alert->id }}', '{{ $alert->product_id }}', '{{ $alert->severity }}', `{{ addslashes($alert->message) }}`)" 
                                class="text-indigo-600 hover:underline font-bold">تعديل</button>
                            
                            <form action="{{ route('drug-alerts.destroy', $alert->id) }}" method="POST" class="inline-block" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-rose-600 hover:underline font-bold">حذف</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="p-6 text-center text-slate-400">لا توجد تنبيهات دوائية مسجلة.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function editItem(id, productId, severity, message) {
        const form = document.getElementById('crud-form');
        form.action = `/drug-alerts/${id}`;
        document.getElementById('form-method').value = 'PUT';
        
        document.getElementById('product_id').value = productId;
        document.getElementById('severity').value = severity;
        document.getElementById('message').value = message;
        
        document.getElementById('form-title').innerText = 'تعديل التنبيه الدوائي';
        
        const btn = document.getElementById('submit-btn');
        btn.innerText = 'تحديث التنبيه';
        btn.className = 'w-full py-2.5 bg-emerald-600 text-white rounded-xl text-xs font-bold hover:bg-emerald-700 transition';
        document.getElementById('cancel-btn').classList.remove('hidden');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function resetForm() {
        const form = document.getElementById('crud-form');
        form.action = "{{ route('drug-alerts.store') }}";
        document.getElementById('form-method').value = 'POST';
        form.reset();
        
        document.getElementById('form-title').innerText = 'إضافة تنبيه دوائي جديد';
        
        const btn = document.getElementById('submit-btn');
        btn.innerText = 'حفظ التنبيه';
        btn.className = 'w-full py-2.5 bg-indigo-600 text-white rounded-xl text-xs font-bold hover:bg-indigo-700 transition';
        document.getElementById('cancel-btn').classList.add('hidden');
    }
</script>
@endsection