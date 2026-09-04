@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-6">
    
    <!-- فورم الإضافة والتعديل -->
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 h-fit">
        <h3 id="form-title" class="text-base font-black text-slate-800 mb-4">تسجيل حركة مخزنية جديدة</h3>

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

        <form id="crud-form" action="{{ route('inventory-transactions.store') }}" method="POST" class="space-y-4">
            @csrf
            <input type="hidden" id="form-method" name="_method" value="POST">

            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">المستودع</label>
                <select id="warehouse_id" name="warehouse_id" required class="w-full px-4 py-2 rounded-xl border border-slate-200 text-xs bg-white">
                    <option value="">-- اختر المستودع --</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">المنتج</label>
                <select id="product_id" name="product_id" required class="w-full px-4 py-2 rounded-xl border border-slate-200 text-xs bg-white">
                    <option value="">-- اختر المنتج --</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}">{{ $product->name ?? $product->title }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">نوع الحركة (Type)</label>
                <input type="text" id="type" name="type" required placeholder="مثال: توريد، صرف، تسوية..." class="w-full px-4 py-2 rounded-xl border border-slate-200 text-xs">
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">الكمية الداخلة (Qty In)</label>
                    <input type="number" step="0.01" id="qty_in" name="qty_in" value="0" required class="w-full px-4 py-2 rounded-xl border border-slate-200 text-xs">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">الكمية الخارجة (Qty Out)</label>
                    <input type="number" step="0.01" id="qty_out" name="qty_out" value="0" required class="w-full px-4 py-2 rounded-xl border border-slate-200 text-xs">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">الرصيد بعد الحركة (Balance After)</label>
                <input type="number" step="0.01" id="balance_after" name="balance_after" value="0" required class="w-full px-4 py-2 rounded-xl border border-slate-200 text-xs">
            </div>

            <div class="flex items-center gap-2 pt-2">
                <button type="submit" id="submit-btn" class="w-full py-2.5 bg-indigo-600 text-white rounded-xl text-xs font-bold hover:bg-indigo-700 transition">حفظ الحركة</button>
                <button type="button" id="cancel-btn" onclick="resetForm()" class="hidden w-full py-2.5 bg-slate-100 text-slate-600 rounded-xl text-xs font-bold transition">إلغاء</button>
            </div>
        </form>
    </div>

    <!-- جدول العرض -->
    <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h3 class="text-base font-black text-slate-800">سجل الحركات المخزنية</h3>
            <span class="text-xs text-slate-400 font-semibold">الإجمالي: {{ $transactions->count() }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-right border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 border-b border-slate-200">
                        <th class="p-4 font-bold">المستودع</th>
                        <th class="p-4 font-bold">المنتج</th>
                        <th class="p-4 font-bold">النوع</th>
                        <th class="p-4 font-bold">داخل / خارج</th>
                        <th class="p-4 font-bold">الرصيد</th>
                        <th class="p-4 font-bold text-center">التحكم</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($transactions as $trx)
                    <tr class="hover:bg-slate-50/50">
                        <td class="p-4 font-bold text-slate-800">{{ $trx->warehouse->name ?? '---' }}</td>
                        <td class="p-4 text-slate-600">{{ $trx->product->name ?? $trx->product->title ?? '---' }}</td>
                        <td class="p-4">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-600">
                                {{ $trx->type }}
                            </span>
                        </td>
                        <td class="p-4 text-slate-600">
                            <span class="text-emerald-600 font-bold">+{{ $trx->qty_in }}</span> / 
                            <span class="text-rose-600 font-bold">-{{ $trx->qty_out }}</span>
                        </td>
                        <td class="p-4 font-black text-slate-800">{{ $trx->balance_after }}</td>
                        <td class="p-4 text-center space-x-2 space-x-reverse">
                            <button type="button" 
                                onclick="editItem('{{ $trx->id }}', '{{ $trx->warehouse_id }}', '{{ $trx->product_id }}', '{{ $trx->type }}', '{{ $trx->qty_in }}', '{{ $trx->qty_out }}', '{{ $trx->balance_after }}')" 
                                class="text-indigo-600 hover:underline font-bold">تعديل</button>
                            
                            <form action="{{ route('inventory-transactions.destroy', $trx->id) }}" method="POST" class="inline-block" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-rose-600 hover:underline font-bold">حذف</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="p-6 text-center text-slate-400">لا توجد حركات مخزنية مسجلة.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function editItem(id, warehouseId, productId, type, qtyIn, qtyOut, balanceAfter) {
        const form = document.getElementById('crud-form');
        form.action = `/inventory-transactions/${id}`;
        document.getElementById('form-method').value = 'PUT';
        
        document.getElementById('warehouse_id').value = warehouseId;
        document.getElementById('product_id').value = productId;
        document.getElementById('type').value = type;
        document.getElementById('qty_in').value = qtyIn;
        document.getElementById('qty_out').value = qtyOut;
        document.getElementById('balance_after').value = balanceAfter;
        
        document.getElementById('form-title').innerText = 'تعديل الحركة المخزنية';
        
        const btn = document.getElementById('submit-btn');
        btn.innerText = 'تحديث البيانات';
        btn.className = 'w-full py-2.5 bg-emerald-600 text-white rounded-xl text-xs font-bold hover:bg-emerald-700 transition';
        document.getElementById('cancel-btn').classList.remove('hidden');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function resetForm() {
        const form = document.getElementById('crud-form');
        form.action = "{{ route('inventory-transactions.store') }}";
        document.getElementById('form-method').value = 'POST';
        form.reset();
        
        document.getElementById('form-title').innerText = 'تسجيل حركة مخزنية جديدة';
        
        const btn = document.getElementById('submit-btn');
        btn.innerText = 'حفظ الحركة';
        btn.className = 'w-full py-2.5 bg-indigo-600 text-white rounded-xl text-xs font-bold hover:bg-indigo-700 transition';
        document.getElementById('cancel-btn').classList.add('hidden');
    }
</script>
@endsection