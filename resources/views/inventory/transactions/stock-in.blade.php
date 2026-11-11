<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-extrabold text-xl text-slate-800 leading-tight">
                إدخال بضاعة للمخزون (Stock-In)
            </h2>
            <a href="{{ route('inventory.transactions.index') }}" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-800 text-xs font-bold rounded-xl transition-all">
                ← العودة للحركات المخزنية
            </a>
        </div>
    </x-slot>

    <div class="py-2">
        <div class="max-w-7xl mx-auto">
            
            @if($errors->any())
                <div class="mb-4 p-4 bg-rose-50 border border-rose-200 text-rose-600 rounded-2xl text-xs font-semibold">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('inventory.transactions.store-in') }}" method="POST" class="space-y-6">
                @csrf

                <!-- بيانات الفاتورة العامة -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">المستودع</label>
                        <select name="warehouse_id" class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 py-2.5">
                            <option value="">المستودع الرئيسي (افتراضي)</option>
                            @foreach($warehouses ?? [] as $warehouse)
                                <option value="{{ $warehouse->id }}" @selected(old('warehouse_id') == $warehouse->id)>{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">رقم المرجع / الفاتورة (Reference Number)</label>
                        <input type="text" name="reference_number" value="{{ old('reference_number') }}" placeholder="مثال: INV-2026-001" 
                            class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 py-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">ملاحظات</label>
                        <input type="text" name="notes" value="{{ old('notes') }}" placeholder="ملاحظات حول شحنة التوريد..." 
                            class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 py-2.5">
                    </div>
                </div>

                <!-- جدول المنتجات الواردة -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                        <h3 class="font-bold text-slate-800 text-sm">عنصر البضاعة الواردة</h3>
                        <button type="button" id="add-row" class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-600 text-xs font-bold rounded-xl transition-all">
                            + إضافة منتج آخر
                        </button>
                    </div>

                    <div id="items-container" class="space-y-3">
                        <!-- صف عنصر افتراضي -->
                        <div class="item-row grid grid-cols-1 md:grid-cols-12 gap-3 items-center bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <div class="md:col-span-6">
                                <label class="block text-[10px] font-bold text-slate-500 mb-1">المنتج / الدواء</label>
                                <select name="items[0][product_id]" required class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 py-2">
                                    <option value="">اختر المنتج...</option>
                                    @foreach($products as $product)
                                        <option value="{{ $product->id }}">{{ $product->name }} (المتوفر حالياً: {{ $product->stock_quantity ?? 0 }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-[10px] font-bold text-slate-500 mb-1">الكمية</label>
                                <input type="number" name="items[0][quantity]" required min="1" value="1" 
                                    class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 py-2">
                            </div>
                            <div class="md:col-span-3">
                                <label class="block text-[10px] font-bold text-slate-500 mb-1">سعر الشراء (للحدة)</label>
                                <input type="number" step="0.01" name="items[0][purchase_price]" min="0" placeholder="0.00" 
                                    class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 py-2">
                            </div>
                            <div class="md:col-span-1 flex items-end h-full pt-5">
                                <button type="button" class="remove-row w-full py-2 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-xl text-xs font-bold transition-all flex items-center justify-center">
                                    ✕
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- زر الحفظ -->
                <div class="flex justify-end">
                    <button type="submit" class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-100 transition-all">
                        حفظ وتحديث المخزون
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- سكريبت جافاسكريبت لإضافة وحذف الصفوف ديناميكياً -->
    @push('scripts')
    <script>
        let rowIndex = 1;
        document.getElementById('add-row').addEventListener('click', function() {
            const container = document.getElementById('items-container');
            const newRow = document.createElement('div');
            newRow.className = 'item-row grid grid-cols-1 md:grid-cols-12 gap-3 items-center bg-slate-50 p-4 rounded-xl border border-slate-100';
            newRow.innerHTML = `
                <div class="md:col-span-6">
                    <select name="items[${rowIndex}][product_id]" required class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 py-2">
                        <option value="">اختر المنتج...</option>
                        @foreach($products as $product)
                            <option value="{{ $product->id }}">{{ $product->name }} (المتوفر حالياً: {{ $product->stock_quantity ?? 0 }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <input type="number" name="items[${rowIndex}][quantity]" required min="1" value="1" 
                        class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 py-2">
                </div>
                <div class="md:col-span-3">
                    <input type="number" step="0.01" name="items[${rowIndex}][purchase_price]" min="0" placeholder="0.00" 
                        class="w-full text-xs rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 py-2">
                </div>
                <div class="md:col-span-1">
                    <button type="button" class="remove-row w-full py-2 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-xl text-xs font-bold transition-all flex items-center justify-center">
                        ✕
                    </button>
                </div>
            `;
            container.appendChild(newRow);
            rowIndex++;
        });

        document.addEventListener('click', function(e) {
            if (e.target && e.target.classList.contains('remove-row')) {
                const rows = document.querySelectorAll('.item-row');
                if (rows.length > 1) {
                    e.target.closest('.item-row').remove();
                } else {
                    alert('يجب أن تحتوي الفاتورة على عنصر واحد على الأقل.');
                }
            }
        });
    </script>
    @endpush
</x-app-layout>