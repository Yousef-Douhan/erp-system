@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-extrabold text-slate-800">إدارة المبيعات (Sales)</h2>
            <p class="text-xs text-slate-400 mt-1">متابعة فواتير المبيعات، المبالغ المدفوعة، وحالة الدفع</p>
        </div>
        <button onclick="openModal('addModal')" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl shadow-md transition-all flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            إضافة فاتورة جديدة
        </button>
    </div>

    <!-- Alert Success -->
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 text-emerald-600 text-xs font-semibold border border-emerald-100">
            {{ session('success') }}
        </div>
    @endif

    <!-- Errors Validation -->
    @if($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 text-rose-600 text-xs font-semibold border border-rose-100">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- فلترة المبيعات حسب التاريخ (من - إلى) ومبيعات اليوم -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-wrap items-center justify-between gap-4">
        <form method="GET" action="{{ route('sales.index') }}" class="flex flex-wrap items-center gap-3 w-full lg:w-auto">
            
            <!-- من تاريخ -->
            <div class="flex items-center gap-2">
                <label class="text-xs font-semibold text-slate-600">من تاريخ:</label>
                <input type="date" name="start_date" value="{{ request('start_date') }}" class="rounded-xl border-slate-200 text-xs py-2">
            </div>

            <!-- إلى تاريخ -->
            <div class="flex items-center gap-2">
                <label class="text-xs font-semibold text-slate-600">إلى تاريخ:</label>
                <input type="date" name="end_date" value="{{ request('end_date') }}" class="rounded-xl border-slate-200 text-xs py-2">
            </div>

            <!-- أزرار البحث والتنفيذ -->
            <div class="flex items-center gap-2">
                <button type="submit" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold rounded-xl transition-all">
                    فلترة النتائج
                </button>
                
                @if(request('start_date') || request('end_date') || request('filter'))
                    <a href="{{ route('sales.index') }}" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold rounded-xl transition-all">
                        إعادة ضبط
                    </a>
                @endif
            </div>
        </form>

        <!-- زر مبيعات اليوم السريع -->
        <div>
            <a href="{{ route('sales.index', ['filter' => 'today', 'start_date' => date('Y-m-d'), 'end_date' => date('Y-m-d')]) }}" 
               class="px-4 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-600 text-xs font-bold rounded-xl transition-all border border-emerald-200 flex items-center gap-1.5 shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                مبيعات اليوم فقط
            </a>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50 text-slate-500 text-xs font-bold text-right">
                <tr>
                    <th class="px-6 py-3"># ID</th>
                    <th class="px-6 py-3">رقم الفاتورة</th>
                    <th class="px-6 py-3">العميل</th>
                    <th class="px-6 py-3">طريقة الدفع</th>
                    <th class="px-6 py-3">الإجمالي</th>
                    <th class="px-6 py-3">المدفوع</th>
                    <th class="px-6 py-3">الحالة</th>
                    <th class="px-6 py-3 text-center">التحكم</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 text-sm text-slate-700">
                @forelse($sales as $sale)
                    <tr>
                        <td class="px-6 py-4 font-bold text-slate-500">{{ $sale->id }}</td>
                        <td class="px-6 py-4 font-semibold text-slate-900">{{ $sale->invoice_number }}</td>
                        <td class="px-6 py-4">
                            {{ $sale->customer->name ?? $sale->customer_name ?? '---' }}
                            @if($sale->wallet_phone)
                                <span class="block text-xs text-slate-400">جوال: {{ $sale->wallet_phone }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if($sale->payment_method == 'cash') نقدي 
                            @elseif($sale->payment_method == 'palestine_bank') بنك فلسطين 
                            @elseif($sale->payment_method == 'pal_pay') بال بي 
                            @elseif($sale->payment_method == 'jawwal_pay') جوال بي 
                            @else {{ $sale->payment_method }} @endif
                        </td>
                        <td class="px-6 py-4 font-bold text-slate-900">{{ number_format($sale->total, 2) }}</td>
                        <td class="px-6 py-4 font-bold text-emerald-600">{{ number_format($sale->paid_amount, 2) }}</td>
                        <td class="px-6 py-4">
                            @if($sale->status == 'paid')
                                <span class="px-2.5 py-1 text-xs font-bold bg-emerald-50 text-emerald-600 rounded-lg">مدفوعة</span>
                            @elseif($sale->status == 'partial')
                                <span class="px-2.5 py-1 text-xs font-bold bg-amber-50 text-amber-600 rounded-lg">جزئي</span>
                            @else
                                <span class="px-2.5 py-1 text-xs font-bold bg-rose-50 text-rose-600 rounded-lg">غير مدفوعة</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center flex items-center justify-center gap-2">
                            <!-- زر العرض -->
                            <a href="{{ route('sales.show', $sale->id) }}" class="text-emerald-600 hover:text-emerald-800 text-xs font-semibold px-2.5 py-1 bg-emerald-50 rounded-lg">عرض</a>

                            <!-- زر التعديل -->
                            <button onclick='openEditModal(@json($sale))' class="text-indigo-600 hover:text-indigo-800 text-xs font-semibold px-2.5 py-1 bg-indigo-50 rounded-lg">تعديل</button>

                            <!-- زر الحذف -->
                            <form action="{{ route('sales.destroy', $sale->id) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من حذف الفاتورة؟');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-rose-600 hover:text-rose-800 text-xs font-semibold px-2.5 py-1 bg-rose-50 rounded-lg">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-6 py-8 text-center text-slate-400 text-sm">
                            لا توجد فواتير مبيعات مسجلة حتى الآن.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Modal إضافة فاتورة جديدة -->
<div id="addModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 hidden">
    <div class="bg-white rounded-2xl p-6 w-full max-w-xl space-y-4 shadow-xl max-h-[90vh] overflow-y-auto">
        <h3 class="text-lg font-bold text-slate-800">إضافة فاتورة مبيعات جديدة</h3>
        
        <form action="{{ route('sales.store') }}" method="POST" class="space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">المنظمة</label>
                    <select name="organization_id" class="w-full rounded-xl border-slate-200 text-sm" required>
                        <option value="">اختر المنظمة</option>
                        @foreach($organizations as $org)
                            <option value="{{ $org->id }}">{{ $org->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">الفرع</label>
                    <select name="branch_id" class="w-full rounded-xl border-slate-200 text-sm" required>
                        <option value="">اختر الفرع</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">العميل (اختيار من القائمة)</label>
                    <select name="customer_id" id="add_customer_id" class="w-full rounded-xl border-slate-200 text-sm" onchange="fillCustomerName(this, 'add_customer_name')">
                        <option value="">اختر العميل</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" data-name="{{ $customer->name }}">{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">أو اسم العميل (نصي)</label>
                    <input type="text" name="customer_name" id="add_customer_name" class="w-full rounded-xl border-slate-200 text-sm" placeholder="اسم العميل الخارجي">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">طريقة الدفع</label>
                    <select name="payment_method" class="w-full rounded-xl border-slate-200 text-sm" required>
                        <option value="cash">نقدي (Cash)</option>
                        <option value="palestine_bank">بنك فلسطين</option>
                        <option value="pal_pay">بال بي</option>
                        <option value="jawwal_pay">جوال بي</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">رقم جوال المحفظة / التحويل</label>
                    <input type="text" name="wallet_phone" class="w-full rounded-xl border-slate-200 text-sm" placeholder="059xxxxxxx">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">الموظف المسؤول</label>
                <select name="employee_id" class="w-full rounded-xl border-slate-200 text-sm" required>
                    <option value="">اختر الموظف</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}">{{ $emp->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">رقم الفاتورة</label>
                <input type="text" name="invoice_number" class="w-full rounded-xl border-slate-200 text-sm" required>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">الإجمالي</label>
                    <input type="number" step="0.01" name="total" class="w-full rounded-xl border-slate-200 text-sm" min="0" required>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">المدفوع</label>
                    <input type="number" step="0.01" name="paid_amount" value="0" class="w-full rounded-xl border-slate-200 text-sm" min="0" required>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">الحالة</label>
                    <select name="status" class="w-full rounded-xl border-slate-200 text-sm" required>
                        <option value="unpaid">غير مدفوعة</option>
                        <option value="partial">جزئي</option>
                        <option value="paid">مدفوعة</option>
                    </select>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeModal('addModal')" class="px-4 py-2 bg-slate-100 text-slate-600 text-xs font-semibold rounded-xl">إلغاء</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-xs font-semibold rounded-xl">حفظ</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal تعديل الفاتورة -->
<div id="editModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 hidden">
    <div class="bg-white rounded-2xl p-6 w-full max-w-xl space-y-4 shadow-xl max-h-[90vh] overflow-y-auto">
        <h3 class="text-lg font-bold text-slate-800">تعديل فاتورة المبيعات</h3>
        
        <form id="editForm" method="POST" class="space-y-4">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">المنظمة</label>
                    <select name="organization_id" id="edit_organization_id" class="w-full rounded-xl border-slate-200 text-sm" required>
                        <option value="">اختر المنظمة</option>
                        @foreach($organizations as $org)
                            <option value="{{ $org->id }}">{{ $org->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">الفرع</label>
                    <select name="branch_id" id="edit_branch_id" class="w-full rounded-xl border-slate-200 text-sm" required>
                        <option value="">اختر الفرع</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">العميل</label>
                    <select name="customer_id" id="edit_customer_id" class="w-full rounded-xl border-slate-200 text-sm">
                        <option value="">اختر العميل</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">اسم العميل (نصي)</label>
                    <input type="text" name="customer_name" id="edit_customer_name" class="w-full rounded-xl border-slate-200 text-sm">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">طريقة الدفع</label>
                    <select name="payment_method" id="edit_payment_method" class="w-full rounded-xl border-slate-200 text-sm" required>
                        <option value="cash">نقدي (Cash)</option>
                        <option value="palestine_bank">بنك فلسطين</option>
                        <option value="pal_pay">بال بي</option>
                        <option value="jawwal_pay">جوال بي</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">رقم جوال المحفظة / التحويل</label>
                    <input type="text" name="wallet_phone" id="edit_wallet_phone" class="w-full rounded-xl border-slate-200 text-sm">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">الموظف المسؤول</label>
                <select name="employee_id" id="edit_employee_id" class="w-full rounded-xl border-slate-200 text-sm" required>
                    <option value="">اختر الموظف</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}">{{ $emp->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">رقم الفاتورة</label>
                <input type="text" name="invoice_number" id="edit_invoice_number" class="w-full rounded-xl border-slate-200 text-sm" required>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">الإجمالي</label>
                    <input type="number" step="0.01" name="total" id="edit_total" class="w-full rounded-xl border-slate-200 text-sm" min="0" required>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">المدفوع</label>
                    <input type="number" step="0.01" name="paid_amount" id="edit_paid_amount" class="w-full rounded-xl border-slate-200 text-sm" min="0" required>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600, mb-1">الحالة</label>
                    <select name="status" id="edit_status" class="w-full rounded-xl border-slate-200 text-sm" required>
                        <option value="unpaid">غير مدفوعة</option>
                        <option value="partial">جزئي</option>
                        <option value="paid">مدفوعة</option>
                    </select>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeModal('editModal')" class="px-4 py-2 bg-slate-100 text-slate-600 text-xs font-semibold rounded-xl">إلغاء</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-xs font-semibold rounded-xl">تحديث</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

    // ملء اسم العميل تلقائياً عند اختياره من القائمة
    function fillCustomerName(selectElement, targetInputId) {
        let selectedOption = selectElement.options[selectElement.selectedIndex];
        let customerName = selectedOption.getAttribute('data-name') || '';
        document.getElementById(targetInputId).value = customerName;
    }

    function openEditModal(sale) {
        document.getElementById('edit_organization_id').value = sale.organization_id;
        document.getElementById('edit_branch_id').value = sale.branch_id;
        document.getElementById('edit_customer_id').value = sale.customer_id || '';
        document.getElementById('edit_customer_name').value = sale.customer_name || '';
        document.getElementById('edit_payment_method').value = sale.payment_method || 'cash';
        document.getElementById('edit_wallet_phone').value = sale.wallet_phone || '';
        document.getElementById('edit_employee_id').value = sale.employee_id;
        document.getElementById('edit_invoice_number').value = sale.invoice_number;
        document.getElementById('edit_total').value = sale.total;
        document.getElementById('edit_paid_amount').value = sale.paid_amount;
        document.getElementById('edit_status').value = sale.status;

        document.getElementById('editForm').action = `/sales/${sale.id}`;
        openModal('editModal');
    }
</script>
@endsection