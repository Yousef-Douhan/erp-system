@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    
    <!-- Top Bar / Back & Actions -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('sales.index') }}" class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 transition-all">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <div>
                <h2 class="text-xl font-extrabold text-slate-800">تفاصيل الفاتورة: {{ $sale->invoice_number }}</h2>
                <p class="text-xs text-slate-400 mt-0.5">تاريخ الإنشاء: {{ $sale->created_at->format('Y-m-d H:i') }}</p>
            </div>
        </div>
        
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('sales.receipt', $sale) }}" target="_blank" class="x-btn is-ghost is-sm">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v8H6z"/></svg>
                إيصال حراري
            </a>

            @if (Route::has('returns.create') && auth()->user()->can('return_sales') && $sale->items->contains(fn ($i) => $i->returnableQuantity() > 0))
            <a href="{{ route('returns.create', $sale) }}" class="x-btn is-danger is-sm">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 14 4 9l5-5M4 9h11a5 5 0 0 1 0 10h-3"/></svg>
                مرتجع
            </a>
            @endif


            @can('pay', $sale)
            <button onclick="openModal('payModal')" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-xl shadow-md transition-all flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a1 1 0 11-2 0 1 1 0 012 0z"/></svg>
                تسجيل دفعة (سداد المتبقي)
            </button>
            @endcan
        </div>
    </div>

    <!-- Alert Messages -->
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 text-emerald-600 text-xs font-semibold border border-emerald-100">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 text-rose-600 text-xs font-semibold border border-rose-100">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Invoice Meta Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <!-- بطاقة بيانات العميل -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm space-y-1">
            <span class="text-xs text-slate-400 block mb-1">بيانات العميل</span>
            <div class="text-xs text-slate-700 space-y-1">
                <p><span class="font-semibold text-slate-500">الاسم:</span> <span class="font-bold text-slate-900">{{ $sale->customer->name ?? $sale->customer_name ?? '---' }}</span></p>
                <p><span class="font-semibold text-slate-500">رقم العميل:</span> <span class="font-mono font-bold text-indigo-600">#{{ $sale->customer->id ?? $sale->customer_id ?? '---' }}</span></p>
                <p><span class="font-semibold text-slate-500">رقم الجوال:</span> <span class="font-mono font-bold text-slate-800">{{ $sale->customer->phone ?? $sale->wallet_phone ?? '---' }}</span></p>
            </div>
        </div>

        <!-- بطاقة الفرع والمنظمة -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
            <span class="text-xs text-slate-400 block mb-1">الفرع والمنظمة</span>
            <span class="text-sm font-bold text-slate-800">{{ $sale->branch->name ?? '---' }} ({{ $sale->organization->name ?? '---' }})</span>
        </div>

        <!-- بطاقة الموظف المسؤول -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
            <span class="text-xs text-slate-400 block mb-1">الموظف المسؤول</span>
            <span class="text-sm font-bold text-slate-800">{{ $sale->employee->username ?? '---' }}</span>
        </div>

        <!-- بطاقة حالة وطريقة الدفع المحدثة بالكامل -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm space-y-2">
            <span class="text-xs text-slate-400 block">حالة وطريقة الدفع</span>
            <div class="space-y-1">
                <div>
                    @if($sale->status == 'paid')
                        <span class="px-2.5 py-1 text-xs font-bold bg-emerald-50 text-emerald-600 rounded-lg">مدفوعة</span>
                    @elseif($sale->status == 'partial')
                        <span class="px-2.5 py-1 text-xs font-bold bg-amber-50 text-amber-600 rounded-lg">جزئي</span>
                    @else
                        <span class="px-2.5 py-1 text-xs font-bold bg-rose-50 text-rose-600 rounded-lg">غير مدفوعة</span>
                    @endif
                </div>
                <div class="pt-1 text-xs text-slate-700">
                    <span class="font-semibold text-slate-500">الطريقة:</span> 
                    <span class="font-bold text-slate-900">
                        @php
                            $rawMethod = trim($sale->payment_method ?? $sale->payment_type ?? $sale->gateway ?? 'cash');
                            $methods = [
                                'cash' => 'نقدي (Cash)',
                                'bank_palestine' => 'بنك فلسطين',
                                'palpay' => 'محفظة بال بي (PalPay)',
                                'jawwal_pay' => 'جوال بي (Jawwal Pay)',
                                'debt' => 'بيع آجل (دين على العميل)',
                            ] + ($paymentMethodNames ?? []);
                        @endphp
                        {{ $methods[$rawMethod] ?? ($rawMethod ?: 'نقدي (Cash)') }}
                    </span>
                </div>
                @if($sale->cashRegister)
                    <div class="text-xs text-slate-600">
                        <span class="font-semibold text-slate-500">الخزينة:</span> {{ $sale->cashRegister->name }}
                    </div>
                @endif
                @if($sale->prescription)
                    <div class="text-xs text-slate-600">
                        <span class="font-semibold text-slate-500">الوصفة الطبية:</span> د. {{ $sale->prescription->doctor_name }}{{ $sale->prescription->diagnosis ? ' — ' . $sale->prescription->diagnosis : '' }}
                    </div>
                @endif
                @if($sale->payer_name || $sale->wallet_phone || $sale->payer_account || $sale->transfer_ref)
                    <div class="text-xs text-slate-600 space-y-0.5">
                        <span class="font-semibold text-slate-500 block">المحوِّل:</span>
                        @if($sale->payer_name)<div>{{ $sale->payer_name }}</div>@endif
                        @if($sale->wallet_phone)<div class="font-mono" dir="ltr" style="text-align:right">{{ $sale->wallet_phone }}</div>@endif
                        @if($sale->payer_account)<div>حساب: <span class="font-mono">{{ $sale->payer_account }}</span></div>@endif
                        @if($sale->transfer_ref)<div>رقم العملية: <span class="font-mono">{{ $sale->transfer_ref }}</span></div>@endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Sale Items Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden space-y-4">
        <div class="px-6 pt-6 flex items-center justify-between">
            <h3 class="text-base font-bold text-slate-800">عنصر الفاتورة (Products / Items)</h3>
        </div>

        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50 text-slate-500 text-xs font-bold text-right">
                <tr>
                    <th class="px-6 py-3">المنتج</th>
                    <th class="px-6 py-3">الكمية</th>
                    <th class="px-6 py-3">سعر الوحدة</th>
                    <th class="px-6 py-3">الخصم</th>
                    <th class="px-6 py-3">الإجمالي الصافي</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 text-sm text-slate-700">
                @forelse($sale->items as $item)
                    <tr>
                        <td class="px-6 py-4 font-semibold text-slate-900">
                            {{ $item->product->name ?? 'منتج غير معروف' }}
                            @if($item->batch)
                                <div class="text-xs font-normal text-slate-500 mt-0.5">
                                    دفعة: {{ $item->batch->batch_number }}@if($item->batch->expiry_date) — تنتهي {{ $item->batch->expiry_date }}@endif
                                </div>
                            @endif
                        </td>
                        <td class="px-6 py-4">{{ rtrim(rtrim(number_format($item->quantity, 2, '.', ''), '0'), '.') }} {{ $item->unit_name }}</td>
                        <td class="px-6 py-4">{{ number_format($item->unit_price, 2) }}</td>
                        <td class="px-6 py-4 text-rose-500">{{ number_format($item->discount, 2) }}</td>
                        <td class="px-6 py-4 font-bold text-slate-900">{{ number_format($item->total, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-slate-400 text-sm">
                            لا توجد منتجات مضافة لهذه الفاتورة حتى الآن.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Totals Summary Bar -->
        <div class="bg-slate-50 px-6 py-4 border-t border-slate-200 flex justify-end items-center gap-8">
            <div>
                <span class="text-xs text-slate-500 block">إجمالي الفاتورة:</span>
                <span class="text-lg font-black text-slate-900">{{ number_format($sale->total, 2) }}</span>
            </div>
            @if ((float) $sale->returned_amount > 0)
            <div>
                <span class="text-xs text-slate-500 block">المرتجعات:</span>
                <span class="text-lg font-black text-amber-700">− {{ number_format($sale->returned_amount, 2) }}</span>
            </div>
            @endif
            <div>
                <span class="text-xs text-slate-500 block">المبلغ المدفوع:</span>
                <span class="text-lg font-black text-emerald-600">{{ number_format($sale->paid_amount, 2) }}</span>
            </div>
            <div>
                <span class="text-xs text-slate-500 block">المتبقي:</span>
                <span class="text-lg font-black text-rose-600">{{ number_format(\App\Services\SaleService::remaining($sale), 2) }}</span>
            </div>
        </div>
    </div>

    @if ($sale->returns->isNotEmpty())
    <section class="erp-card">
        <div class="erp-card-head"><h2>مرتجعات هذه الفاتورة</h2></div>
        <div class="x-table-wrap">
            <table class="x-table">
                <thead><tr><th>رقم المرتجع</th><th>التاريخ</th><th class="n">القيمة</th><th class="n">رُد نقداً</th><th class="n">خُفّض من الدين</th><th>المخزون</th></tr></thead>
                <tbody>
                    @foreach ($sale->returns as $ret)
                        <tr>
                            <td><a href="{{ route('returns.show', $ret) }}" class="num">{{ $ret->return_number }}</a></td>
                            <td class="muted">{{ $ret->created_at->format('Y-m-d H:i') }}</td>
                            <td class="n">{{ number_format($ret->total, 2) }}</td>
                            <td class="n">{{ number_format($ret->refund_amount, 2) }}</td>
                            <td class="n">{{ number_format($ret->debt_reduction, 2) }}</td>
                            <td><span class="x-badge {{ $ret->restocked ? 'is-good' : 'is-warn' }}">{{ $ret->restocked ? 'أُعيد للمخزون' : 'تالف' }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
    @endif
</div>

<!-- Modal تسجيل دفعة (سداد المتبقي) مع اختيار طريقة الدفع -->
<div id="payModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 hidden">
    <div class="bg-white rounded-2xl p-6 w-full max-w-md space-y-4 shadow-xl">
        <h3 class="text-lg font-bold text-slate-800">تسجيل دفعة جديدة</h3>
        <p class="text-xs text-slate-400">المبلغ المتبقي الحالي: <span class="font-bold text-rose-600">{{ number_format(\App\Services\SaleService::remaining($sale), 2) }}</span></p>
        
        <form action="{{ route('sales.pay', $sale->id)}}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">المبلغ المراد دفعه</label>
                <input type="number" step="0.01" name="amount" value="{{ \App\Services\SaleService::remaining($sale) }}" max="{{ \App\Services\SaleService::remaining($sale) }}" class="w-full rounded-xl border-slate-200 text-sm" min="0.01" required>
            </div>

            <!-- خيارات الدفع (من طرق الدفع المفعّلة) -->
            <div class="space-y-2">
                <label class="block text-xs font-bold text-slate-700">طريقة الدفع:</label>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    @php $methodsList = $payMethods->isNotEmpty() ? $payMethods : collect([(object) ['code' => 'cash', 'name' => 'نقدي', 'requires_wallet' => false]]); @endphp
                    @foreach ($methodsList as $pm)
                        <label class="flex items-center gap-2 p-2 bg-slate-50 rounded-xl border border-slate-200 cursor-pointer">
                            <input type="radio" name="payment_method" value="{{ $pm->code }}" data-wallet="{{ $pm->requires_wallet ? 1 : 0 }}" data-kind="{{ $pm->kind ?? ($pm->requires_wallet ? 'wallet' : 'cash') }}" @checked($loop->first) onchange="togglePayWalletField()"> {{ $pm->name }}
                        </label>
                    @endforeach
                </div>
            </div>

            <!-- بيانات المحوِّل عند الدفع ببنك/محفظة: للمطابقة مع كشف الحساب -->
            <div id="payWalletField" class="hidden space-y-2 p-3 rounded-xl border border-dashed border-slate-300 bg-slate-50">
                <p class="text-xs font-bold text-slate-700">بيانات المحوِّل (للمطابقة مع كشف الحساب)</p>
                <input type="text" name="payer_name" id="payPayerName" maxlength="120" value="{{ old('payer_name', $sale->customer->name ?? '') }}" class="w-full rounded-xl border-slate-200 text-xs" placeholder="اسم المحوِّل كما يظهر في الحوالة">
                <input type="text" name="wallet_phone" id="payWalletPhone" maxlength="30" dir="ltr" value="{{ old('wallet_phone') }}" class="w-full rounded-xl border-slate-200 text-xs text-right" placeholder="رقم الجوال / المحفظة 059xxxxxxx">
                <div class="grid grid-cols-2 gap-2">
                    <input type="text" name="payer_account" maxlength="60" dir="ltr" value="{{ old('payer_account') }}" class="w-full rounded-xl border-slate-200 text-xs text-right" placeholder="رقم الحساب (اختياري)">
                    <input type="text" name="transfer_ref" maxlength="60" dir="ltr" value="{{ old('transfer_ref') }}" class="w-full rounded-xl border-slate-200 text-xs text-right" placeholder="رقم العملية (اختياري)">
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeModal('payModal')" class="px-4 py-2 bg-slate-100 text-slate-600 text-xs font-semibold rounded-xl">إلغاء</button>
                <button type="submit" class="px-4 py-2 bg-emerald-600 text-white text-xs font-semibold rounded-xl">تأكيد الدفع</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

    // إظهار أو إخفاء حقل رقم المحفظة في نافذة تسديد الدفعة
    function togglePayWalletField() {
        const checked = document.querySelector('input[name="payment_method"]:checked');
        const wallet = checked && checked.dataset.wallet === '1';
        const field = document.getElementById('payWalletField');
        field.classList.toggle('hidden', !wallet);
        const bank = checked && checked.dataset.kind === 'bank';
        const name = document.getElementById('payPayerName'), phone = document.getElementById('payWalletPhone');
        name.required = wallet;
        name.placeholder = bank ? 'اسم صاحب الحساب البنكي (إجباري)' : 'اسم المحوِّل كما يظهر في المحفظة (إجباري)';
        phone.required = wallet && !bank;
        phone.placeholder = bank ? 'رقم الجوال (اختياري)' : 'رقم الجوال / المحفظة 059xxxxxxx (إجباري)';
    }
    document.addEventListener('DOMContentLoaded', togglePayWalletField);
</script>
@endsection