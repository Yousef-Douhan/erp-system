@extends('layouts.app')

@section('title', 'مرتجع للمورد من فاتورة ' . $purchase->invoice_number)

@section('content')
@php $remaining = $purchase->remaining(); @endphp

<div class="x-page">
    <div class="x-head">
        <div>
            <h2>مرتجع للمورد من الفاتورة <span class="num">{{ $purchase->invoice_number }}</span></h2>
            <p>المورد: <b>{{ $purchase->supplier->name ?? '—' }}</b>. القيمة تُحسب بسعر الشراء، وتُخصم أولاً من المتبقي علينا في هذه الفاتورة.</p>
        </div>
        <div class="x-actions">
            <a href="{{ route('purchases.index', ['show' => $purchase->id]) }}" class="x-btn is-ghost">رجوع للفاتورة</a>
        </div>
    </div>

    @include('partials.x-flash')

    <form method="POST" action="{{ route('purchase-returns.store', $purchase) }}" class="x-split" id="prForm">
        @csrf

        <section class="erp-card">
            <div class="erp-card-head">
                <h2>أصناف الفاتورة</h2>
                <button type="button" class="x-btn is-light is-sm" id="prAll">إرجاع كل المتاح</button>
            </div>
            <div class="x-table-wrap">
                <table class="x-table">
                    <thead>
                        <tr>
                            <th>الصنف / الدفعة</th>
                            <th class="n">المشترى</th>
                            <th class="n">أُرجع سابقاً</th>
                            <th class="n">الموجود الآن</th>
                            <th class="n">سعر الشراء</th>
                            <th style="text-align:center">الكمية المرتجعة</th>
                            <th class="n">القيمة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lines as $line)
                            @php $item = $line['item']; $max = $line['max']; @endphp
                            <tr>
                                <td>
                                    <strong>{{ $item->product->name ?? 'صنف محذوف' }}</strong>
                                    <div class="muted">
                                        @if ($item->batch_number) دفعة {{ $item->batch_number }} @endif
                                        @if ($item->expiry_date) · تنتهي {{ \Illuminate\Support\Carbon::parse($item->expiry_date)->toDateString() }} @endif
                                    </div>
                                </td>
                                <td class="n">{{ (int) $item->quantity }}</td>
                                <td class="n">{{ (int) $item->returned_quantity ?: '—' }}</td>
                                <td class="n">{{ $line['available'] }}</td>
                                <td class="n">{{ number_format($item->unit_cost, 2) }}</td>
                                <td style="text-align:center">
                                    @if ($max > 0)
                                        <input type="number" name="quantities[{{ $item->id }}]" value="{{ old('quantities.' . $item->id) }}"
                                               min="0" max="{{ $max }}" step="1" placeholder="0"
                                               class="x-qty pr-qty" data-price="{{ (float) $item->unit_cost }}" data-max="{{ $max }}" aria-label="الكمية المرتجعة من {{ $item->product->name ?? '' }}">
                                        <div class="muted">حتى {{ $max }}</div>
                                    @elseif ($item->returnableQuantity() === 0)
                                        <span class="x-badge">أُرجع بالكامل</span>
                                    @else
                                        <span class="x-badge is-warn">بِيع كله</span>
                                    @endif
                                </td>
                                <td class="n pr-line">0.00</td>
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
                    <strong class="num" id="prTotal">0.00<small>₪</small></strong>
                </div>
            </div>

            <div style="display:grid;gap:8px;font-size:14px">
                <div style="display:flex;justify-content:space-between"><span style="color:var(--muted)">صافي الفاتورة</span><b class="num">{{ number_format($purchase->netTotal(), 2) }}</b></div>
                <div style="display:flex;justify-content:space-between"><span style="color:var(--muted)">دفعنا للمورد</span><b class="num">{{ number_format($purchase->paid_amount, 2) }}</b></div>
                <div style="display:flex;justify-content:space-between"><span style="color:var(--muted)">المتبقي علينا</span><b class="num">{{ number_format($remaining, 2) }}</b></div>
                <hr style="border:0;border-top:1px solid var(--line-soft);margin:4px 0">
                <div style="display:flex;justify-content:space-between"><span>يُخصم من المستحق للمورد</span><b class="num" id="prDebt">0.00</b></div>
                <div style="display:flex;justify-content:space-between"><span>زائد عن المتبقي</span><b class="num" id="prExcess" style="color:var(--teal)">0.00</b></div>
            </div>

            <div id="prExcessBox" hidden><div style="display:grid;gap:8px">
                <span style="font-size:13px;font-weight:600">الفاتورة مدفوعة، فماذا يحدث بالزائد؟</span>
                <label class="x-check"><input type="radio" name="excess_mode" value="credit" @checked(old('excess_mode', 'credit') === 'credit')> يبقى رصيداً لنا عند المورد</label>
                <label class="x-check"><input type="radio" name="excess_mode" value="cash" @checked(old('excess_mode') === 'cash')> المورد ردّه نقداً</label>
                <div class="x-field" id="prRegisterField" hidden>
                    <label for="prRegister">يدخل المبلغ إلى خزينة</label>
                    <select name="cash_register_id" id="prRegister">
                        @foreach ($registers as $r)
                            <option value="{{ $r->id }}" @selected((int) old('cash_register_id') === $r->id)>{{ $r->name }} — {{ number_format($r->current_balance, 2) }} ₪</option>
                        @endforeach
                    </select>
                </div>
            </div></div>

            <div class="x-field">
                <label for="prReason">سبب الإرجاع</label>
                <textarea name="reason" id="prReason" maxlength="500" placeholder="مثال: قريب الانتهاء / تالف عند الاستلام / خطأ في الطلبية">{{ old('reason') }}</textarea>
            </div>

            <button type="submit" class="x-btn" id="prSubmit" disabled>تسجيل المرتجع</button>
        </aside>
    </form>
</div>

<script>
(function () {
    const remaining = {{ $remaining }}, net = {{ $purchase->netTotal() }};
    const inputs = [...document.querySelectorAll('.pr-qty')];
    const money = n => (Math.round(n * 100) / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const modeCash = () => document.querySelector('input[name="excess_mode"][value="cash"]').checked;

    function refresh() {
        let total = 0;
        inputs.forEach(i => {
            let q = parseInt(i.value, 10) || 0;
            const max = parseInt(i.dataset.max, 10);
            if (q > max) { q = max; i.value = max; }
            if (q < 0) { q = 0; i.value = ''; }
            const line = Math.round(q * parseFloat(i.dataset.price) * 100) / 100;
            i.closest('tr').querySelector('.pr-line').textContent = money(line);
            total += line;
        });
        total = Math.min(total, net);
        const debt = Math.min(total, remaining), excess = Math.round((total - debt) * 100) / 100;

        document.getElementById('prTotal').innerHTML = money(total) + '<small>₪</small>';
        document.getElementById('prDebt').textContent = money(debt);
        document.getElementById('prExcess').textContent = money(excess);
        document.getElementById('prExcessBox').hidden = excess <= 0;
        document.getElementById('prRegisterField').hidden = !(excess > 0 && modeCash());
        document.getElementById('prSubmit').disabled = total <= 0;
    }

    inputs.forEach(i => i.addEventListener('input', refresh));
    document.querySelectorAll('input[name="excess_mode"]').forEach(r => r.addEventListener('change', refresh));
    document.getElementById('prAll').addEventListener('click', () => { inputs.forEach(i => i.value = i.dataset.max); refresh(); });
    document.getElementById('prForm').addEventListener('submit', e => {
        const t = document.getElementById('prTotal').textContent;
        if (!confirm('تأكيد تسجيل مرتجع للمورد بقيمة ' + t + '؟ ستخرج الكميات من المخزون ولا يمكن التراجع.')) e.preventDefault();
    });
    refresh();
})();
</script>
@endsection
