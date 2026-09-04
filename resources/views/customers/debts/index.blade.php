@extends('layouts.app')

@section('content')
    <div class="max-w-6xl mx-auto p-4 space-y-4" dir="rtl">
        
        <!-- رأس الصفحة + بطاقة إجمالي ديون كل الزبائن -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white p-4 rounded-xl shadow-sm flex flex-col justify-center col-span-1 md:col-span-2">
                <h2 class="text-base font-bold text-slate-800">دفتر ديون الحسابات الآجلة للعملاء</h2>
                <p class="text-xs text-slate-500 mt-0.5">إدارة الحسابات، تسديد الدفعات، ومتابعة كشف حساب العملاء والأصناف.</p>
            </div>
            
            <!-- بطاقة إجمالي ديون جميع الزبائن -->
            <div class="bg-gradient-to-l from-rose-500 to-rose-600 p-4 rounded-xl shadow-sm text-white flex items-center justify-between">
                <div>
                    <span class="text-xs text-rose-100 font-semibold block">إجمالي ديون كل الزبائن</span>
                    <span class="text-xl font-black mt-1 block">
                        {{ number_format($totalAllCustomersDebt ?? $customers->sum('balance'), 2) }} ₪
                    </span>
                </div>
                <div class="p-3 bg-white/15 rounded-xl">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- شريط البحث -->
        <div class="bg-white p-4 rounded-xl shadow-sm flex justify-between items-center">
            <span class="text-xs font-bold text-slate-700">قائمة العملاء المدينين</span>
            <form method="GET" action="{{ url()->current() }}" class="flex gap-2">
                <input type="text" name="search" placeholder="بحث بالاسم أو الهاتف..." value="{{ request('search') }}"
                    class="rounded-lg border-slate-200 text-xs py-1.5 px-3">
                <button type="submit" class="px-3 py-1.5 bg-slate-800 text-white rounded-lg text-xs font-bold">بحث</button>

                @if (request('search'))
                    <a href="{{ url()->current() }}"
                        class="px-3 py-1.5 bg-slate-200 text-slate-700 rounded-lg text-xs font-bold flex items-center">إلغاء البحث</a>
                @endif
            </form>
        </div>

        <!-- جدول العملاء -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <table class="w-full text-right border-collapse text-xs">
                <thead class="bg-slate-100 text-slate-700 border-b">
                    <tr>
                        <th class="p-3">اسم العميل</th>
                        <th class="p-3">رقم الهاتف</th>
                        <th class="p-3">إجمالي الدين (الرصيد الحالي)</th>
                        <th class="p-3 text-center">إجراءات الحساب</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($customers as $customer)
                        <tr class="hover:bg-slate-50">
                            <td class="p-3 font-bold text-slate-800">{{ $customer->name }}</td>
                            <td class="p-3 text-slate-600">{{ $customer->phone ?? 'لا يوجد' }}</td>
                            <td class="p-3 font-black text-rose-600">{{ number_format($customer->balance ?? 0, 2) }} ₪</td>
                            <td class="p-3 text-center flex items-center justify-center gap-2">
                                <!-- زر سداد / تعديل الدين -->
                                <button type="button"
                                    onclick="openDebtModal('{{ $customer->id }}', '{{ $customer->name }}', '{{ $customer->balance ?? 0 }}')"
                                    class="px-3 py-1 bg-amber-500 hover:bg-amber-600 text-slate-900 font-bold rounded-lg text-xs shadow-sm">
                                    تسديد / تعديل
                                </button>

                                <!-- زر كشف الحساب والحركات والأصناف والسدادات -->
                                <button type="button"
                                    onclick="openLedgerModal('{{ $customer->id }}', '{{ $customer->name }}')"
                                    class="px-3 py-1 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg text-xs shadow-sm flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012-2m-6 9l2 2 4-4" />
                                    </svg>
                                    كشف الحساب
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="p-4 text-center text-slate-500">لا يوجد عملاء مضافين.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="p-3">
                {{ $customers->links() }}
            </div>
        </div>
    </div>

    <!-- Modal تعديل وسداد الديون -->
    <div id="debtModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 hidden">
        <div class="bg-white rounded-xl p-5 w-full max-w-sm space-y-3 shadow-xl" dir="rtl">
            <h3 class="text-sm font-bold text-slate-800" id="modalCustomerName">تعديل حساب العميل</h3>

            <form id="debtForm" onsubmit="submitDebtForm(event)" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">نوع العملية</label>
                    <select id="debtType" name="type" class="w-full rounded-lg border-slate-200 text-xs" required>
                        <option value="subtract">سداد دفعة (تخفيض من الدين)</option>
                        <option value="add">إضافة دين جديد (تسجيل على الحساب)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">المبلغ</label>
                    <input type="number" step="0.01" id="debtAmount" name="amount"
                        class="w-full rounded-lg border-slate-200 text-xs" required min="0">
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="closeDebtModal()"
                        class="px-3 py-1.5 bg-slate-100 text-slate-600 text-xs font-semibold rounded-lg">إلغاء</button>
                    <button type="submit"
                        class="px-3 py-1.5 bg-emerald-600 text-white text-xs font-semibold rounded-lg">حفظ التعديل</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal كشف الحساب والأصناف والدفعات المسددة -->
    <div id="ledgerModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 hidden">
        <div class="bg-white rounded-2xl p-6 w-full max-w-4xl space-y-4 shadow-2xl max-h-[90vh] flex flex-col" dir="rtl">

            <!-- رأس النافذة مع خانة إجمالي الدين الحالي -->
            <div class="flex justify-between items-center border-b pb-3">
                <div>
                    <h3 class="text-base font-black text-slate-800" id="ledgerModalTitle">كشف حساب العميل</h3>
                    <span class="text-xs font-bold text-rose-600 bg-rose-50 px-2.5 py-1 rounded-lg border border-rose-200 mt-1 inline-block"
                        id="ledgerTotalBalance">
                        إجمالي الدين الحالي: 0.00 ₪
                    </span>
                </div>
                <button type="button" onclick="closeLedgerModal()" class="text-slate-400 hover:text-slate-600 font-bold text-lg">✕</button>
            </div>

            <div class="flex-1 overflow-y-auto space-y-4">
                <div>
                    <h4 class="text-xs font-bold text-slate-700 mb-2">📦 الفواتير، الأصناف، وسدادات الدفعات:</h4>
                    <div class="border rounded-xl overflow-hidden">
                        <table class="w-full text-right border-collapse text-xs">
                            <thead class="bg-slate-100 text-slate-700 font-bold">
                                <tr>
                                    <th class="p-2.5">نوع الحركة / التاريخ</th>
                                    <th class="p-2.5">التفاصيل / الأصناف أو الدفعة</th>
                                    <th class="p-2.5">المبلغ الكلي / المدفوع</th>
                                    <th class="p-2.5">المتبقي / ملاحظات</th>
                                </tr>
                            </thead>
                            <tbody id="ledgerSalesTableBody" class="divide-y divide-slate-100">
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-slate-400">جاري التحميل...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="flex justify-end pt-2 border-t">
                <button type="button" onclick="closeLedgerModal()"
                    class="px-4 py-2 bg-slate-800 text-white text-xs font-bold rounded-xl">إغلاق</button>
            </div>
        </div>
    </div>

    <script>
        let currentCustomerId = null;

        function openDebtModal(id, name, balance) {
            currentCustomerId = id;
            document.getElementById('modalCustomerName').innerText = 'تعديل حساب: ' + name;
            document.getElementById('debtModal').classList.remove('hidden');
        }

        function closeDebtModal() {
            document.getElementById('debtModal').classList.add('hidden');
        }

        // فتح نافذة كشف الحساب وجلب البيانات عبر الـ AJAX
        function openLedgerModal(id, name) {
            currentCustomerId = id;
            document.getElementById('ledgerModalTitle').innerText = 'كشف حساب الحركات والسدادات: ' + name;
            document.getElementById('ledgerTotalBalance').innerText = 'إجمالي الدين الحالي: جاري الحساب...';
            document.getElementById('ledgerModal').classList.remove('hidden');

            let tbody = document.getElementById('ledgerSalesTableBody');
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-6 text-slate-400">جاري جلب تفاصيل الحركات والسدادات...</td></tr>';

            fetch('/customers/' + id + '/ledger', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.customer) {
                    let customerBalance = parseFloat(data.customer.balance || 0).toFixed(2);
                    document.getElementById('ledgerTotalBalance').innerText = 'إجمالي الدين الحالي: ' + customerBalance + ' ₪';
                }

                tbody.innerHTML = '';
                let transactions = [];

                // 1. إضافة الفواتير
                if (data.sales && data.sales.length > 0) {
                    data.sales.forEach(sale => {
                        transactions.push({
                            type: 'sale',
                            date: sale.created_at,
                            data: sale
                        });
                    });
                }

                // 2. إضافة حركات السداد والإضافة من جدول customer_transactions
                if (data.payments && data.payments.length > 0) {
                    data.payments.forEach(payment => {
                        transactions.push({
                            type: payment.type === 'add' ? 'debt_add' : 'payment',
                            date: payment.created_at,
                            data: payment
                        });
                    });
                }

                if (transactions.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center py-6 text-slate-400">لا توجد فواتير أو حركات سداد مسجلة لهذا العميل حتى الآن.</td></tr>';
                    return;
                }

                // ترتيب الحركات تنازلياً حسب التاريخ (الأحدث أولاً)
                transactions.sort((a, b) => new Date(b.date) - new Date(a.date));

                transactions.forEach(tx => {
                    if (tx.type === 'sale') {
                        let sale = tx.data;
                        let itemsHtml = '<ul class="list-disc list-inside space-y-1">';
                        if (sale.items && sale.items.length > 0) {
                            sale.items.forEach(item => {
                                let productName = item.product ? item.product.name : 'منتج محذوف';
                                itemsHtml += `<li><span class="font-bold text-slate-800">${productName}</span> (الكمية: ${item.quantity} | السعر: ${item.unit_price} ₪)</li>`;
                            });
                        } else {
                            itemsHtml += '<span class="text-slate-400">لا توجد تفاصيل أصناف</span>';
                        }
                        itemsHtml += '</ul>';

                        let saleTotal = parseFloat(sale.total || 0);
                        let salePaid = parseFloat(sale.paid_amount || 0);
                        if (isNaN(salePaid) || salePaid > saleTotal) salePaid = 0;
                        let saleRemaining = saleTotal - salePaid;

                        tbody.innerHTML += `
                            <tr class="hover:bg-slate-50 border-b">
                                <td class="p-3 font-semibold text-slate-700">
                                    <span class="px-2.5 py-1 rounded-md text-[10px] font-bold bg-indigo-50 text-indigo-700">فاتورة مبيعات #${sale.id}</span>
                                    <br><span class="text-[10px] text-slate-400 mt-1 block">${sale.created_at}</span>
                                </td>
                                <td class="p-3">${itemsHtml}</td>
                                <td class="p-3 font-bold text-slate-800">إجمالي: ${saleTotal.toFixed(2)} ₪</td>
                                <td class="p-3 text-slate-600">المتبقي: <span class="text-rose-600 font-bold">${saleRemaining.toFixed(2)} ₪</span></td>
                            </tr>
                        `;
                    } else if (tx.type === 'payment') {
                        let payment = tx.data;
                        let payAmount = parseFloat(payment.amount || 0).toFixed(2);
                        tbody.innerHTML += `
                            <tr class="hover:bg-emerald-50/50 bg-emerald-50/25 border-b">
                                <td class="p-3 font-semibold text-emerald-800">
                                    <span class="px-2.5 py-1 rounded-md text-[10px] font-bold bg-emerald-100 text-emerald-800">سداد دفعة دين</span>
                                    <br><span class="text-[10px] text-slate-400 mt-1 block">${payment.created_at || ''}</span>
                                </td>
                                <td class="p-3 text-slate-700 font-medium" colspan="2">
                                    <span class="text-emerald-700 font-bold">قبض نقد / تخفيض من الدين</span>
                                    <p class="text-[11px] text-slate-500 mt-0.5">${payment.notes || 'تسديد دفعة مالية'}</p>
                                </td>
                                <td class="p-3 font-black text-emerald-600">
                                    المبلغ المسدد: -${payAmount} ₪
                                </td>
                            </tr>
                        `;
                    } else if (tx.type === 'debt_add') {
                        let payment = tx.data;
                        let addAmount = parseFloat(payment.amount || 0).toFixed(2);
                        tbody.innerHTML += `
                            <tr class="hover:bg-amber-50/50 bg-amber-50/25 border-b">
                                <td class="p-3 font-semibold text-amber-800">
                                    <span class="px-2.5 py-1 rounded-md text-[10px] font-bold bg-amber-100 text-amber-800">إضافة دين يدوي</span>
                                    <br><span class="text-[10px] text-slate-400 mt-1 block">${payment.created_at || ''}</span>
                                </td>
                                <td class="p-3 text-slate-700 font-medium" colspan="2">
                                    <span class="text-amber-700 font-bold">تسجيل دين مباشر على الحساب</span>
                                    <p class="text-[11px] text-slate-500 mt-0.5">${payment.notes || 'إضافة دين جديد'}</p>
                                </td>
                                <td class="p-3 font-black text-amber-600">
                                    المبلغ المضاف: +${addAmount} ₪
                                </td>
                            </tr>
                        `;
                    }
                });
            })
            .catch(error => {
                console.error('Error:', error);
                tbody.innerHTML = '<tr><td colspan="4" class="text-center py-6 text-rose-500">حدث خطأ أثناء جلب بيانات كشف الحساب.</td></tr>';
            });
        }

        function closeLedgerModal() {
            document.getElementById('ledgerModal').classList.add('hidden');
        }

        function submitDebtForm(event) {
            event.preventDefault();
            let type = document.getElementById('debtType').value;
            let amount = document.getElementById('debtAmount').value;
            let token = document.querySelector('input[name="_token"]').value;

            fetch('/customers/debts/' + currentCustomerId, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    type: type,
                    amount: amount
                })
            })
            .then(async response => {
                if (response.ok) {
                    location.reload();
                } else {
                    let data = await response.json();
                    alert('خطأ من الخادم: ' + (data.message || JSON.stringify(data)));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('حدث خطأ غير متوقع.');
            });
        }
    </script>
@endsection