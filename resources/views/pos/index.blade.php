@extends('layouts.app')

@section('content')
    <style>
        .ts-wrapper.single .ts-control {
            min-height: 38px;
            padding: 6px 12px;
            border-radius: 0.5rem;
            border-color: rgb(226 232 240);
            font-size: 0.75rem;
            background-color: #fff;
            box-shadow: none !important;
        }

        .ts-wrapper.single .ts-control input {
            font-size: 0.75rem !important;
        }

        .ts-dropdown {
            border-radius: 0.5rem;
            border-color: rgb(226 232 240);
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            font-size: 0.75rem;
            z-index: 50;
        }
    </style>

    <body class="bg-slate-100 font-sans antialiased text-slate-800">

        <div id="posWrapper" class="min-h-screen p-4 flex flex-col gap-4 max-w-7xl mx-auto pb-28 transition-all duration-300">

            <!-- Top Header Bar -->
            <div class="bg-white border border-slate-200 rounded-xl px-4 py-2.5 flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-3">
                    <span
                        class="px-3 py-1 bg-amber-400 text-slate-900 font-black text-xs rounded-lg shadow-sm flex items-center gap-1">
                        ⭐ BETA
                    </span>
                    <span class="px-3 py-1 bg-emerald-600 text-white font-bold text-xs rounded-lg">New Sale</span>
                    <span class="text-xs font-semibold text-slate-600">مبيعات اليوم</span>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('debts.index') }}"
                        class="px-3 py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-bold rounded-lg transition-all flex items-center gap-1.5 border border-rose-200"
                        title="إدارة الديون وسدادها">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>الديون والسداد</span>
                    </a>
                    <button type="button" onclick="toggleExpandMode()" id="expandBtn"
                        class="p-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-lg transition-all"
                        title="تكبير الواجهة">
                        <svg id="expandIcon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                        </svg>
                    </button>
                    <a href="{{ route('sales.index') }}"
                        class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-lg transition-all">
                        إدارة الفواتير
                    </a>
                </div>
            </div>

            <!-- Main POS Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-start flex-1">

                <!-- 1. شريط التصنيفات الجانبي -->
                <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 p-2 space-y-2 h-[78vh] overflow-y-auto shadow-sm"
                    x-data="{ selectedCategory: 'all' }">
                    <button type="button" @click="selectedCategory = 'all'"
                        :class="selectedCategory === 'all' ? 'bg-slate-900 text-white' :
                            'bg-emerald-600 hover:bg-emerald-700 text-white'"
                        class="w-full py-2.5 text-xs font-bold rounded-lg text-center transition-all shadow-sm">
                        All (الكل)
                    </button>

                    @isset($categories)
                        @foreach ($categories as $category)
                            <button type="button" @click="selectedCategory = '{{ $category->id }}'"
                                :class="selectedCategory === '{{ $category->id }}' ? 'bg-slate-900 text-white' :
                                    'bg-emerald-600 hover:bg-emerald-700 text-white'"
                                class="w-full py-2 text-xs font-medium rounded-lg text-center transition-all truncate px-2">
                                {{ $category->name }}
                            </button>
                        @endforeach
                    @endisset
                </div>

                <!-- 2. قسم المنتجات والبحث -->
                <div
                    class="lg:col-span-5 flex flex-col bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden h-[78vh]">
                    <div class="p-3 border-b border-slate-200 bg-slate-50 flex gap-2">
                        <input type="text" id="productSearch" placeholder="Search product or scan barcode..."
                            class="flex-1 rounded-lg border-slate-200 text-xs focus:ring-emerald-500 focus:border-emerald-500">
                        <select class="w-1/3 rounded-lg border-slate-200 text-xs">
                            <option value="">Select Item</option>
                        </select>
                    </div>

                    <div id="productsGrid"
                        class="flex-1 p-3 overflow-y-auto grid grid-cols-2 md:grid-cols-3 gap-3 content-start">
                        @foreach ($products as $product)
                            @php
                                $productPrice = $product->selling_price ?? ($product->price ?? 0);
                                $stock = $product->stock_quantity ?? 0;
                                $isOutOfStock = $stock <= 0;
                            @endphp

                            <div onclick="{{ $isOutOfStock ? '' : "addToCart('" . $product->id . "', '" . addslashes($product->name) . "', " . $productPrice . ', ' . $stock . ')' }}"
                                class="product-card bg-white border rounded-xl p-2.5 cursor-pointer transition-all flex flex-col justify-between space-y-2 group relative shadow-xs 
                        {{ $isOutOfStock ? 'bg-slate-100 border-slate-200 opacity-65 cursor-not-allowed' : 'hover:border-emerald-500 border-slate-200' }}"
                                data-id="{{ $product->id }}"
                                data-name="{{ strtolower($product->name) }}"
                                data-barcode="{{ strtolower($product->barcode ?? '') }}"
                                data-price="{{ $productPrice }}"
                                data-stock="{{ $stock }}">

                                <div class="flex justify-between items-center">
                                    <span
                                        class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $isOutOfStock ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-800' }}">
                                        {{ $isOutOfStock ? 'نفد المخزون' : 'متوفر: ' . $stock }}
                                    </span>
                                    <div
                                        class="w-2.5 h-2.5 rounded-full {{ $isOutOfStock ? 'bg-red-500' : 'bg-emerald-600' }}">
                                    </div>
                                </div>

                                <div
                                    class="h-16 bg-slate-50 rounded-lg flex items-center justify-center border border-slate-100">
                                    <svg class="w-8 h-8 text-slate-300 group-hover:text-emerald-500 transition-colors"
                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                            d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                    </svg>
                                </div>
                                <div class="text-center">
                                    <span
                                        class="text-xs font-bold text-slate-800 group-hover:text-emerald-600 block truncate">{{ $product->name }}</span>
                                    <span
                                        class="text-[11px] font-black text-emerald-600">{{ number_format($productPrice, 2) }}
                                        ₪</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- 3. جدول السلة وإدارة الفاتورة -->
                <div
                    class="lg:col-span-5 flex flex-col bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden h-[78vh]">

                    <div class="p-2.5 border-b border-slate-200 bg-slate-50 flex items-center gap-2">
                        <input type="text" id="barcodeInput" placeholder="Barcode or QR-scan"
                            class="w-1/3 rounded-lg border-slate-200 text-[11px]">
                        <span class="text-[10px] font-bold text-slate-400">OR</span>

                        <select name="customer_id" id="pos_customer_id" form="posForm"
                            onchange="updateCustomerPreviousBalance(this)"
                            class="flex-1 rounded-lg border-slate-200 text-xs">
                            <option value="" data-balance="0">Walking Customer (زبون نقدي)</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}" data-balance="{{ $customer->balance ?? 0 }}">
                                    {{ $customer->name }} (رصيد سابق: {{ $customer->balance ?? 0 }} ₪)
                                </option>
                            @endforeach
                        </select>

                        <button type="button" onclick="openModal('addCustomerModal')"
                            class="p-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold"
                            title="إضافة عميل">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto">
                        <table class="w-full text-right border-collapse text-[11px]">
                            <thead class="bg-slate-100 text-slate-700 sticky top-0 border-b border-slate-200 font-bold">
                                <tr>
                                    <th class="p-2">Item Information *</th>
                                    <th class="p-2">Batch</th>
                                    <th class="p-2">Quantity *</th>
                                    <th class="p-2">Price *</th>
                                    <th class="p-2">Discount %</th>
                                    <th class="p-2">Total</th>
                                    <th class="p-2 text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody id="cartTableBody" class="divide-y divide-slate-100">
                                <tr>
                                    <td colspan="7" class="text-center text-slate-400 py-12">السلة فارغة حالياً.. أضف
                                        منتجات من القائمة</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- إجماليات الفاتورة الجانبية -->
                    <div class="border-t border-slate-200 p-3 bg-slate-50 space-y-1.5 text-xs">
                        <div class="flex justify-between items-center text-slate-600">
                            <span>Invoice Discount:</span>
                            <input type="number" step="0.01" id="cartDiscount" value="0"
                                class="w-28 rounded-lg border-slate-200 text-xs py-1 text-center"
                                oninput="calculateTotals()">
                        </div>
                        <div class="flex justify-between text-slate-600">
                            <span>Total Discount:</span>
                            <span id="totalDiscountText" class="font-bold">0.00</span>
                        </div>
                        <div class="flex justify-between text-slate-600">
                            <span>Total VAT:</span>
                            <span class="font-bold">0.00</span>
                        </div>
                        <div class="flex justify-between text-sm font-black text-slate-900 border-t pt-1">
                            <span>Grand Total (الفاتورة الحالية):</span>
                            <span id="grandTotalText" class="text-emerald-600">0.00</span>
                        </div>
                        <div class="flex justify-between text-slate-600 text-xs">
                            <span>Previous Balance (الرصيد السابق):</span>
                            <span id="previousBalanceText" class="font-bold text-amber-600">0.00</span>
                        </div>
                        <div class="flex justify-between text-sm font-black text-slate-900 border-t pt-1">
                            <span>Total Payable (الإجمالي الكلي):</span>
                            <span id="totalPayableText" class="text-indigo-600">0.00</span>
                        </div>
                        <div class="flex justify-between text-slate-600 text-xs">
                            <span>Change:</span>
                            <span id="changeText">0.00</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- الشريط السفلي الثابت للدفع -->
            <form action="{{ route('pos.store') }}" method="POST" id="posForm"
                class="fixed bottom-0 left-0 right-0 bg-white border-t border-slate-300 p-3 shadow-lg z-40 px-6 space-y-2">
                @csrf
                <div id="hiddenCartInputs"></div>
                <input type="hidden" name="discount" id="formDiscountValue" value="0">
                <input type="hidden" name="payment_method" id="selectedPaymentMethod" value="cash">

                <div id="walletPhoneContainer"
                    class="hidden flex items-center gap-2 bg-amber-50 p-2 rounded-lg border border-amber-200 w-fit">
                    <span class="text-xs font-bold text-amber-800">رقم المحفظة / الحساب:</span>
                    <input type="text" name="wallet_phone" id="walletPhoneInput" placeholder="أدخل الرقم..."
                        class="w-48 rounded-lg border-amber-300 text-xs py-1 text-center bg-white">
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-6 text-sm font-black">
                        <div>
                            <span class="text-slate-500 text-xs ml-2">Net Total:</span>
                            <span id="netTotalBottom" class="text-slate-900 text-base">0.00</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-slate-500 text-xs">Paid Amount:</span>
                            <input type="number" step="0.01" name="paid_amount" id="paidAmountInput" value="0"
                                class="w-32 rounded-lg border-slate-300 text-sm font-bold text-emerald-600 text-center py-1.5"
                                required oninput="calculateChange()">
                        </div>
                        <div>
                            <span class="text-slate-500 text-xs ml-2">Due Amount:</span>
                            <span id="dueAmountBottom" class="text-rose-600 text-base">0.00</span>
                        </div>
                    </div>

                    <button type="button" onclick="submitToDebtSystem()"
                        class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white font-black text-xs rounded-xl shadow-md transition-all flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012-2m-6 9l2 2 4-4" />
                        </svg>
                        <span>ترحيل إلى الديون (على الآجل)</span>
                    </button>

                    <div class="flex items-center gap-2 flex-wrap justify-end">
                        <button type="button" onclick="setFullPaid()"
                            class="px-4 py-2 bg-amber-400 hover:bg-amber-500 text-slate-900 font-bold text-xs rounded-xl shadow-sm transition-all">
                            Full Paid
                        </button>

                        @isset($paymentMethods)
                            @foreach ($paymentMethods as $method)
                                @php
                                    $methodKey = $method->slug ?? $method->name;
                                    $methodName = $method->display_name ?? $method->name;
                                    $color = strtolower($method->color ?? 'emerald');
                                    $bgClass = match ($color) {
                                        'purple' => 'bg-purple-600 hover:bg-purple-700 text-white',
                                        'cyan' => 'bg-cyan-600 hover:bg-cyan-700 text-white',
                                        'indigo' => 'bg-indigo-600 hover:bg-indigo-700 text-white',
                                        'blue' => 'bg-blue-600 hover:bg-blue-700 text-white',
                                        'amber' => 'bg-amber-500 hover:bg-amber-600 text-slate-900',
                                        default => 'bg-emerald-600 hover:bg-emerald-700 text-white',
                                    };
                                    $requiresWallet =
                                        ($method->requires_wallet ?? false) ||
                                        str_contains(strtolower($methodKey), 'palpay') ||
                                        str_contains(strtolower($methodKey), 'jawwal') ||
                                        str_contains(strtolower($methodKey), 'bank') ||
                                        str_contains(strtolower($methodName), 'بنك') ||
                                        str_contains(strtolower($methodName), 'محفظة');
                                @endphp

                                <button type="button"
                                    onclick="submitWithPaymentMethod('{{ $methodKey }}', {{ $requiresWallet ? 'true' : 'false' }})"
                                    class="px-4 py-2 font-bold text-xs rounded-xl shadow-sm transition-all {{ $bgClass }}">
                                    {{ $methodName }}
                                </button>
                            @endforeach
                        @endisset
                    </div>
                </div>
            </form>
        </div>

        <!-- Modal إضافة عميل سريع -->
        <div id="addCustomerModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 hidden">
            <div class="bg-white rounded-xl p-5 w-full max-w-sm space-y-3 shadow-xl">
                <h3 class="text-sm font-bold text-slate-800">إضافة عميل جديد</h3>
                <form action="{{ route('pos.customer.store') }}" method="POST" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">اسم العميل</label>
                        <input type="text" name="name" class="w-full rounded-lg border-slate-200 text-xs" required>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">رقم الهاتف</label>
                        <input type="text" name="phone" class="w-full rounded-lg border-slate-200 text-xs">
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" onclick="closeModal('addCustomerModal')"
                            class="px-3 py-1.5 bg-slate-100 text-slate-600 text-xs font-semibold rounded-lg">إلغاء</button>
                        <button type="submit"
                            class="px-3 py-1.5 bg-emerald-600 text-white text-xs font-semibold rounded-lg">حفظ</button>
                    </div>
                </form>
            </div>
        </div>

<script>
    let cart = [];
    let selectedCustomerPreviousBalance = 0;
    let currentSelectedPaymentMethod = '{{ $paymentMethods->first()->slug ?? ($paymentMethods->first()->name ?? 'cash') }}';

    function showToast(message, type = 'success') {
        let toast = document.createElement('div');
        let bgColor = type === 'success' ? 'bg-emerald-500' : 'bg-rose-500';
        toast.className = `fixed top-10 left-1/2 transform -translate-x-1/2 px-4 py-2 rounded-lg text-white text-sm font-bold shadow-lg z-[100] transition-opacity duration-300 ${bgColor}`;
        toast.innerText = message;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 2000);
    }

    function toggleExpandMode() {
        let wrapper = document.getElementById('posWrapper');
        if (wrapper.classList.contains('max-w-7xl')) {
            wrapper.classList.remove('max-w-7xl');
            wrapper.classList.add('max-w-full', 'px-2');
        } else {
            wrapper.classList.add('max-w-7xl');
            wrapper.classList.remove('max-w-full', 'px-2');
        }
    }

    function updateCustomerPreviousBalance(selectElement) {
        let selectedOption = selectElement.options[selectElement.selectedIndex];
        selectedCustomerPreviousBalance = parseFloat(selectedOption.getAttribute('data-balance')) || 0;
        document.getElementById('previousBalanceText').innerText = selectedCustomerPreviousBalance.toFixed(2);
        calculateTotals();
    }

    function setPaymentMethod(method, requiresWallet = false) {
        currentSelectedPaymentMethod = method;
        document.getElementById('selectedPaymentMethod').value = method;

        let walletContainer = document.getElementById('walletPhoneContainer');
        let walletInput = document.getElementById('walletPhoneInput');

        if (requiresWallet) {
            walletContainer.classList.remove('hidden');
            walletInput.setAttribute('required', 'required');
            walletInput.focus();
        } else {
            walletContainer.classList.add('hidden');
            walletInput.removeAttribute('required');
            walletInput.value = '';
        }
    }

    function submitWithPaymentMethod(method, requiresWallet) {
        setPaymentMethod(method, requiresWallet);
        let walletInput = document.getElementById('walletPhoneInput');
        
        if (requiresWallet && !walletInput.value.trim()) {
            showToast('الرجاء إدخال رقم المحفظة أو الحساب أولاً', 'error');
            walletInput.focus();
            return;
        }
        document.getElementById('posForm').submit();
    }

    // --- نظام الاستماع للباركود الذكي ---
    let barcodeBuffer = '';
    let lastKeyTime = Date.now();

    document.addEventListener('keydown', function(e) {
        let activeElement = document.activeElement;
        let activeTag = activeElement.tagName.toLowerCase();

        if (e.key === 'Enter') {
            if (activeTag === 'input' && activeElement.id !== 'walletPhoneInput' && activeElement.id !== 'paidAmountInput') {
                e.preventDefault();
            }
        }

        if (['input', 'textarea', 'select'].includes(activeTag)) {
            return; 
        }

        let currentTime = Date.now();

        if (currentTime - lastKeyTime > 50) {
            barcodeBuffer = ''; 
        }
        
        if (e.key === 'Enter') {
            e.preventDefault();
            let query = barcodeBuffer.trim();
            if (query) {
                processBarcodeQuery(query);
                barcodeBuffer = '';
            }
            return;
        }

        if (e.key.length === 1) {
            barcodeBuffer += e.key;
        }
        lastKeyTime = currentTime;
    });

    // تم إصلاح دالة المعالجة بحيث تتأكد 100% من مطابقة الباركود أو الاسم وإضافة المنتج للسلة فعلياً
    function processBarcodeQuery(queryText) {
        let query = queryText.trim().toLowerCase();
        let productCards = document.querySelectorAll('.product-card');
        let matchedCard = null;

        for (let card of productCards) {
            let cardBarcode = (card.getAttribute('data-barcode') || '').trim().toLowerCase();
            let name = (card.getAttribute('data-name') || '').trim().toLowerCase();
            
            // مطابقة تامة أو بالاحتواء لضمان إيجاد المنتج حتى مع اختلاف بسيط
            if ((cardBarcode && cardBarcode === query) || name === query || name.includes(query)) {
                matchedCard = card;
                break;
            }
        }

        if (matchedCard) {
            let id = matchedCard.getAttribute('data-id');
            let name = matchedCard.getAttribute('data-name');
            let price = parseFloat(matchedCard.getAttribute('data-price')) || 0;
            let stock = parseInt(matchedCard.getAttribute('data-stock')) || 0;

            addToCart(id, name, price, stock);
            showToast('تم إضافة المنتج للفاتورة بنجاح', 'success');
        } else {
            showToast('هذا الباركود غير موجود في المنتجات المعروضة!', 'error');
        }
    }

    document.getElementById('productSearch').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            let query = this.value.trim();
            if (query) {
                processBarcodeQuery(query);
                this.value = '';
                this.dispatchEvent(new Event('input'));
            }
        }
    });

    document.getElementById('barcodeInput')?.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            let query = this.value.trim();
            if (query) {
                processBarcodeQuery(query);
                this.value = '';
            }
        }
    });

    document.getElementById('productSearch').addEventListener('input', function() {
        let term = this.value.toLowerCase().trim();
        let cards = document.querySelectorAll('.product-card');
        cards.forEach(card => {
            let name = card.getAttribute('data-name') || '';
            let barcode = card.getAttribute('data-barcode') || '';
            if (name.includes(term) || barcode.includes(term)) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    });

    function addToCart(id, name, price, stock) {
        let validPrice = parseFloat(price) || 0;
        let availableStock = parseInt(stock) || 0;

        if (availableStock <= 0) {
            showToast('عذراً، هذا المنتج نفد من المخزون!', 'error');
            return;
        }

        let existing = cart.find(item => item.id === id);
        if (existing) {
            if (existing.quantity + 1 > availableStock) {
                showToast('تجاوزت الحد الأقصى للمخزون (' + availableStock + ')', 'error');
                return;
            }
            existing.quantity++;
        } else {
            cart.push({ id, name, price: validPrice, quantity: 1, max_stock: availableStock });
        }
        renderCart();
    }

    function updateQty(id, qty) {
        let item = cart.find(i => i.id === id);
        if (item) {
            let newQty = parseInt(qty) || 1;
            if (newQty > item.max_stock) {
                showToast('الكمية المطلوبة تتجاوز المخزون!', 'error');
                item.quantity = item.max_stock;
            } else {
                item.quantity = Math.max(1, newQty);
            }
            renderCart();
        }
    }

    function changeQty(id, amount) {
        let item = cart.find(i => i.id === id);
        if (item) {
            let newQty = item.quantity + amount;
            if (newQty > item.max_stock) {
                showToast('لا يمكن تجاوز الكمية المتوفرة!', 'error');
                return;
            }
            item.quantity = Math.max(1, newQty);
            renderCart();
        }
    }

    function removeItem(id) {
        cart = cart.filter(i => i.id !== id);
        renderCart();
    }

    function renderCart() {
        let tbody = document.getElementById('cartTableBody');
        let hiddenInputs = document.getElementById('hiddenCartInputs');
        tbody.innerHTML = '';
        hiddenInputs.innerHTML = '';

        if (cart.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-slate-400 py-12">السلة فارغة حالياً.. أضف منتجات من القائمة</td></tr>';
            document.getElementById('netTotalBottom').innerText = '0.00';
            document.getElementById('grandTotalText').innerText = '0.00';
            document.getElementById('totalPayableText').innerText = selectedCustomerPreviousBalance.toFixed(2);
            document.getElementById('paidAmountInput').value = '0';
            document.getElementById('dueAmountBottom').innerText = '0.00';
            document.getElementById('formDiscountValue').value = '0';
            calculateTotals();
            return;
        }

        cart.forEach((item, index) => {
            let itemTotal = item.price * item.quantity;
            tbody.innerHTML += `
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="p-2 font-bold text-slate-800">${item.name}</td>
                    <td class="p-2 text-slate-500 text-[10px]">مخزون: ${item.max_stock}</td>
                    <td class="p-2">
                        <div class="flex items-center gap-1">
                            <input type="number" value="${item.quantity}" onchange="updateQty('${item.id}', this.value)" class="w-12 rounded border-slate-200 text-xs py-0.5 text-center">
                            <div class="flex flex-col">
                                <button type="button" onclick="changeQty('${item.id}', 1)" class="w-4 h-4 bg-emerald-600 text-white rounded-t text-[9px] flex items-center justify-center">+</button>
                                <button type="button" onclick="changeQty('${item.id}', -1)" class="w-4 h-4 bg-rose-500 text-white rounded-b text-[9px] flex items-center justify-center">-</button>
                            </div>
                        </div>
                    </td>
                    <td class="p-2 font-semibold text-slate-700">${item.price.toFixed(2)}</td>
                    <td class="p-2"><input type="number" value="0" class="w-14 rounded border-slate-200 text-xs py-0.5 text-center" readonly disabled></td>
                    <td class="p-2 font-bold text-emerald-600">${itemTotal.toFixed(2)}</td>
                    <td class="p-2 text-center flex items-center justify-center gap-1">
                        <button type="button" onclick="removeItem('${item.id}')" class="p-1 bg-rose-100 hover:bg-rose-200 text-rose-600 rounded">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </td>
                </tr>
            `;

            hiddenInputs.innerHTML += `
                <input type="hidden" name="items[${index}][product_id]" value="${item.id}">
                <input type="hidden" name="items[${index}][quantity]" value="${item.quantity}">
                <input type="hidden" name="items[${index}][unit_price]" value="${item.price}">
                <input type="hidden" name="items[${index}][discount]" value="0">
            `;
        });

        calculateTotals();
    }

    function calculateTotals() {
        let subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        let discount = parseFloat(document.getElementById('cartDiscount').value) || 0;

        document.getElementById('formDiscountValue').value = discount;

        let grandTotal = Math.max(0, subtotal - discount);
        let totalPayable = grandTotal + selectedCustomerPreviousBalance;

        document.getElementById('totalDiscountText').innerText = discount.toFixed(2);
        document.getElementById('grandTotalText').innerText = grandTotal.toFixed(2);
        document.getElementById('netTotalBottom').innerText = totalPayable.toFixed(2);
        document.getElementById('totalPayableText').innerText = totalPayable.toFixed(2);
        
        let currentPaid = parseFloat(document.getElementById('paidAmountInput').value) || 0;
        if(currentPaid === 0 && totalPayable > 0) {
           document.getElementById('paidAmountInput').value = totalPayable.toFixed(2);
        }

        calculateChange();
    }

    function calculateChange() {
        let totalPayable = parseFloat(document.getElementById('totalPayableText').innerText) || 0;
        let paid = parseFloat(document.getElementById('paidAmountInput').value) || 0;
        let due = Math.max(0, totalPayable - paid);
        let change = Math.max(0, paid - totalPayable);

        document.getElementById('dueAmountBottom').innerText = due.toFixed(2);
        document.getElementById('changeText').innerText = change.toFixed(2);
    }

    function setFullPaid() {
        let totalPayable = parseFloat(document.getElementById('totalPayableText').innerText) || 0;
        document.getElementById('paidAmountInput').value = totalPayable.toFixed(2);
        calculateChange();
    }

    function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

    document.addEventListener("DOMContentLoaded", function() {
        new TomSelect("#pos_customer_id", {
            create: false,
            sortField: { field: "text", direction: "asc" },
            placeholder: "ابحث عن العميل بالاسم..."
        });
    });

    function submitToDebtSystem() {
        let customerSelect = document.getElementById('pos_customer_id');

        if (!customerSelect.value || customerSelect.value === "") {
            showToast('الرجاء اختيار اسم الزبون أولاً لترحيل الدين!', 'error');
            return;
        }

        if (cart.length === 0) {
            showToast('السلة فارغة، يرجى إضافة أدوية أولاً!', 'error');
            return;
        }

        document.getElementById('paidAmountInput').value = '0';
        document.getElementById('selectedPaymentMethod').value = 'debt';
        document.getElementById('posForm').submit();
    }
</script>
    </body>
@endsection