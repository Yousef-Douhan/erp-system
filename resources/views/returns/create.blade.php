@extends('layouts.app')

@section('title', 'مرتجع من فاتورة ' . $sale->invoice_number)

@section('content')
@php
    $net = \App\Services\SaleService::netTotal($sale);
    $method = \App\Models\PaymentMethod::where('code', $sale->payment_method)->first();
    $isCash = !($method && $method->requires_wallet);
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
@endphp

<div class="x-page">
    <div class="x-head">
        <div>
            <h2>مرتجع من الفاتورة <span class="num">{{ $sale->invoice_number }}</span></h2>
            <p>حدد الكمية المرتجعة لكل صنف. القيمة تُخصم أولاً من المتبقي على العميل، والباقي يُرد له {{ $isCash ? 'نقداً من الخزينة' : 'بنفس طريقة الدفع' }}.</p>
        </div>
        <div class="x-actions">
            <a href="{{ route('sales.show', $sale) }}" class="x-btn is-ghost">رجوع للفاتورة</a>
        </div>
    </div>

    @include('partials.x-flash')

    <form method="POST" action="{{ route('returns.store', $sale) }}" class="x-split" id="retForm">
        @csrf

        <section class="erp-card">
            <div class="erp-card-head">
                <h2>أصناف الفاتورة</h2>
                <button type="button" class="x-btn is-light is-sm" id="retAll">إرجاع الكل</button>
            </div>
            <div class="x-table-wrap">
                <table class="x-table">
                    <thead>
                        <tr>
                            <th>الصنف</th>
                            <th class="n">المباع</th>
                            <th class="n">أُرجع سابقاً</th>
                            <th class="n">سعر الوحدة الصافي</th>
                            <th style="text-align:center">الكمية المرتجعة</th>
                            <th class="n">القيمة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lines as $line)
                            @php $item = $line['item']; $max = $line['returnable']; @endphp
                            <tr>
                                <td>
                                    <strong>{{ $item->product->name ?? 'صنف محذوف' }}</strong>
                                    @if ($item->unit_name)<div class="muted">بالـ{{ $item->unit_name }}</div>@endif
                                </td>
                                <td class="n">{{ $fmt($item->quantity) }}</td>
                                <td class="n">{{ (float) $item->returned_quantity > 0 ? $fmt($item->returned_quantity) : '—' }}</td>
                                <td class="n">{{ number_format($line['unit_price'], 2) }}</td>
                                <td style="text-align:center">
                                    @if ($max > 0)
                                        <input type="number" name="quantities[{{ $item->id }}]" value="{{ old('quantities.' . $item->id) }}"
                                               min="0" max="{{ $max }}" step="{{ fmod((float) $max, 1) == 0 ? 1 : 0.01 }}" placeholder="0"
                                               class="x-qty ret-qty" data-price="{{ $line['unit_price'] }}" data-max="{{ $max }}" aria-label="الكمية المرتجعة من {{ $item->product->name ?? '' }}">
                                        <div class="muted">من {{ $fmt($max) }}</div>
                                    @else
                                        <span class="x-badge">أُرجع بالكامل</span>
                                    @endif
                                </td>
                                <td class="n ret-line">0.00</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="erp-card x-body is-top" style="display:grid;gap:14px;position:sticky;top:12px">
            <div class="x-stats" style="grid-template-columns:1fr">
                <div class="x-stat is-main">
                    <span>قيمة المرتجع</span>
                    <strong class="num" id="retTotal">0.00<small>₪</small></strong>
                </div>
            </div>

            <div style="display:grid;gap:8px;font-size:14px">
                <div style="display:flex;justify-content:space-between"><span style="color:var(--muted)">صافي الفاتورة</span><b class="num">{{ number_format($net, 2) }}</b></div>
                <div style="display:flex;justify-content:space-between"><span style="color:var(--muted)">المدفوع</span><b class="num">{{ number_format($sale->paid_amount, 2) }}</b></div>
                <div style="display:flex;justify-content:space-between"><span style="color:var(--muted)">المتبقي على العميل</span><b class="num">{{ number_format($remaining, 2) }}</b></div>
                <hr style="border:0;border-top:1px solid var(--line-soft);margin:4px 0">
                <div style="display:flex;justify-content:space-between"><span>يُخصم من دين العميل</span><b class="num" id="retDebt">0.00</b></div>
                <div style="display:flex;justify-content:space-between"><span>يُرد للزبون</span><b class="num" id="retRefund" style="color:var(--red)">0.00</b></div>
            </div>

            @if ($isCash)
                <div class="x-field" id="retRegisterField" hidden>
                    <label for="retRegister">يُصرف المبلغ من خزينة</label>
                    <select name="cash_register_id" id="retRegister">
                        @foreach ($registers as $r)
                            <option value="{{ $r->id }}" @selected((int) old('cash_register_id', $sale->cash_register_id) === $r->id)>{{ $r->name }} — {{ number_format($r->current_balance, 2) }} ₪</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <label class="x-check">
                <input type="hidden" name="restock" value="0">
                <input type="checkbox" name="restock" value="1" @checked(old('restock', '1') === '1')>
                إعادة الأصناف للمخزون
            </label>
            <small style="color:var(--muted);margin-top:-8px">ألغِ التحديد إذا كانت الأصناف تالفة أو لا تصلح للبيع.</small>

            <div class="x-field">
                <label for="retReason">سبب الإرجاع (اختياري)</label>
                <textarea name="reason" id="retReason" maxlength="500" placeholder="مثال: الزبون غيّر الدواء بوصفة جديدة">{{ old('reason') }}</textarea>
            </div>

            <button type="submit" class="x-btn" id="retSubmit" disabled>تسجيل المرتجع</button>
        </aside>
    </form>
</div>

<script>
(function () {
    const net = {{ $net }}, paid = {{ (float) $sale->paid_amount }}, hasCustomer = {{ $sale->customer_id ? 'true' : 'false' }};
    const inputs = [...document.querySelectorAll('.ret-qty')];
    const money = n => (Math.round(n * 100) / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function refresh() {
        let total = 0;
        inputs.forEach(i => {
            let q = parseFloat(i.value) || 0;
            const max = parseFloat(i.dataset.max);
            if (q > max) { q = max; i.value = max; }
            if (q < 0) { q = 0; i.value = ''; }
            const line = Math.round(q * parseFloat(i.dataset.price) * 100) / 100;
            i.closest('tr').querySelector('.ret-line').textContent = money(line);
            total += line;
        });
        total = Math.min(total, net);
        const oldRem = Math.max(0, net - paid), newRem = Math.max(0, net - total - paid);
        const debt = hasCustomer ? Math.round((oldRem - newRem) * 100) / 100 : 0;
        const refund = Math.round((total - debt) * 100) / 100;

        document.getElementById('retTotal').innerHTML = money(total) + '<small>₪</small>';
        document.getElementById('retDebt').textContent = money(debt);
        document.getElementById('retRefund').textContent = money(refund);
        document.getElementById('retSubmit').disabled = total <= 0;
        const reg = document.getElementById('retRegisterField');
        if (reg) reg.hidden = refund <= 0;
    }

    inputs.forEach(i => i.addEventListener('input', refresh));
    document.getElementById('retAll').addEventListener('click', () => { inputs.forEach(i => i.value = i.dataset.max); refresh(); });
    document.getElementById('retForm').addEventListener('submit', e => {
        const t = document.getElementById('retTotal').textContent;
        if (!confirm('تأكيد تسجيل مرتجع بقيمة ' + t + '؟ لا يمكن التراجع عنه.')) e.preventDefault();
    });
    refresh();
})();
</script>
@endsection
