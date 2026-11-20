@extends('layouts.app')

@section('title', 'سداد الموردين')

@section('content')
@php $m = fn ($n) => number_format((float) $n, 2); @endphp

<style>
    .sp-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.15fr); gap: 16px; align-items: start; }
    @media (max-width: 1000px) { .sp-grid { grid-template-columns: 1fr; } }
    .sp-form { display: grid; gap: 14px; }
    .sp-methods { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .sp-methods label { display: flex; align-items: center; gap: 8px; border: 1px solid var(--line); border-radius: var(--radius-sm); padding: 10px 12px; cursor: pointer; font-weight: 600; }
    .sp-methods input[type=radio] { width: 18px; height: 18px; flex: none; margin: 0; padding: 0; box-shadow: none; accent-color: var(--teal); }
    .sp-methods input:checked + span { color: var(--teal-strong); }
    .sp-methods label:has(input:checked) { border-color: var(--teal); background: var(--teal-soft); }
    .sp-alloc td.n b { color: var(--green); }
    .sp-alloc tr.is-skip td { color: var(--muted); }
    .sp-total { display: flex; justify-content: space-between; padding: 10px 12px; background: var(--canvas); border-radius: var(--radius-sm); font-size: 14px; }
    .sp-row { cursor: pointer; }
    .sp-row.is-on { background: var(--teal-soft); }
</style>

<div class="x-page">
    <div class="x-head">
        <div>
            <h2>سداد الموردين</h2>
            <p>ادفع لمورد مبلغاً واحداً، والنظام يوزعه على فواتيره غير المسددة من الأقدم للأحدث، ويخصمه من الخزينة إذا كان نقداً، ويسجله في كشف المورد وفي القيود.</p>
        </div>
    </div>

    @include('partials.x-flash')

    @if ($suppliers->isEmpty())
        <section class="erp-card"><div class="x-empty">لا توجد فواتير شراء غير مسددة. كل الموردين مدفوع لهم بالكامل.</div></section>
    @else
        <div class="x-stats">
            <div class="x-stat is-main"><span>إجمالي المستحق للموردين</span><strong>{{ $m($suppliers->sum('due')) }} <small>₪</small></strong></div>
            <div class="x-stat"><span>موردون لهم مستحقات</span><strong>{{ $suppliers->count() }}</strong></div>
            <div class="x-stat"><span>فواتير غير مسددة</span><strong>{{ $suppliers->sum('invoices_count') }}</strong></div>
        </div>

        <div class="sp-grid">
            <section class="erp-card">
                <div class="erp-card-head"><h2>دفعة جديدة</h2></div>
                <form method="POST" action="{{ route('supplier-payments.store') }}" class="x-body is-top sp-form" id="spForm">
                    @csrf
                    <div class="x-field">
                        <label for="spSupplier">المورد</label>
                        <select name="supplier_id" id="spSupplier" required>
                            @foreach ($suppliers as $s)
                                <option value="{{ $s->id }}" @selected(old('supplier_id', $selected ?: $suppliers->first()->id) == $s->id)>{{ $s->name }} — مستحق {{ $m($s->due) }} ₪</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="x-field">
                        <label for="spAmount">المبلغ (₪)</label>
                        <input type="number" name="amount" id="spAmount" step="0.01" min="0.01" required inputmode="decimal" value="{{ old('amount') }}">
                    </div>

                    <div class="x-table-wrap">
                        <table class="x-table sp-alloc">
                            <thead><tr><th>الفاتورة</th><th>التاريخ</th><th class="n">المتبقي</th><th class="n">سيُدفع</th></tr></thead>
                            <tbody id="spAlloc"></tbody>
                        </table>
                    </div>

                    <div class="x-field">
                        <label>طريقة الدفع</label>
                        <div class="sp-methods">
                            <label><input type="radio" name="payment_method" value="cash" @checked(old('payment_method', 'cash') === 'cash')><span>نقداً من خزينة</span></label>
                            <label><input type="radio" name="payment_method" value="bank" @checked(old('payment_method') === 'bank')><span>تحويل بنكي / شيك</span></label>
                        </div>
                    </div>

                    <div class="x-field" id="spRegisterField">
                        <label for="spRegister">الخزينة</label>
                        <select name="cash_register_id" id="spRegister">
                            @foreach ($registers as $r)
                                <option value="{{ $r->id }}" data-balance="{{ (float) $r->current_balance }}" @selected(old('cash_register_id', request()->query('register')) == $r->id)>{{ $r->name }}{{ $r->branch?->name ? ' — ' . $r->branch->name : '' }} (رصيدها {{ $m($r->current_balance) }} ₪)</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="x-note is-warn" id="spWarn" hidden></div>

                    <div class="x-field">
                        <label for="spRef">رقم المرجع (اختياري)</label>
                        <input type="text" name="reference" id="spRef" maxlength="100" placeholder="رقم الشيك أو الحوالة أو سند القبض من المورد" value="{{ old('reference') }}">
                    </div>
                    <div class="x-field">
                        <label for="spNotes">ملاحظات (اختياري)</label>
                        <input type="text" name="notes" id="spNotes" maxlength="500" value="{{ old('notes') }}">
                    </div>

                    <div class="sp-total"><span>سيُدفع الآن</span><strong id="spSum" class="num">0.00 ₪</strong></div>
                    <div class="x-actions"><button class="x-btn" id="spSubmit">تسجيل الدفعة</button></div>
                </form>
            </section>

            <section class="erp-card">
                <div class="erp-card-head"><h2>المستحق لكل مورد</h2></div>
                <div class="x-table-wrap">
                    <table class="x-table">
                        <thead><tr><th>المورد</th><th class="n">فواتير</th><th>أقدم فاتورة</th><th class="n">المستحق</th></tr></thead>
                        <tbody>
                            @foreach ($suppliers as $s)
                                <tr class="sp-row" data-id="{{ $s->id }}">
                                    <td><strong>{{ $s->name }}</strong>@if ($s->company_name)<div class="muted" style="font-size:12.5px">{{ $s->company_name }}</div>@endif</td>
                                    <td class="n">{{ $s->invoices_count }}</td>
                                    <td class="muted num">{{ substr((string) $s->oldest, 0, 10) }}</td>
                                    <td class="n"><strong>{{ $m($s->due) }}</strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    @endif

    @if ($recent->isNotEmpty())
        <section class="erp-card">
            <div class="erp-card-head"><h2>آخر الدفعات للموردين</h2></div>
            <div class="x-table-wrap">
                <table class="x-table">
                    <thead><tr><th>التاريخ</th><th>المورد</th><th>الفاتورة</th><th>الطريقة</th><th>بواسطة</th><th>المرجع</th><th class="n">المبلغ</th></tr></thead>
                    <tbody>
                        @foreach ($recent as $p)
                            <tr>
                                <td class="muted num">{{ $p->created_at->format('Y-m-d H:i') }}</td>
                                <td>{{ $p->supplier->name ?? '—' }}</td>
                                <td><a href="{{ route('purchases.index', ['show' => $p->purchase_id]) }}">{{ $p->purchase->invoice_number ?? '—' }}</a></td>
                                <td>{{ $p->payment_method === 'cash' ? 'نقداً' . ($p->cashRegister ? ' — ' . $p->cashRegister->name : '') : 'تحويل بنكي' }}</td>
                                <td class="muted">{{ $p->user->username ?? '—' }}</td>
                                <td class="muted">{{ $p->reference ?: '—' }}</td>
                                <td class="n"><strong>{{ $m($p->amount) }}</strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>

@if ($suppliers->isNotEmpty())
<script>
(function () {
    const data = @json($suppliers->mapWithKeys(fn ($s) => [$s->id => ['due' => $s->due, 'open' => $s->open]]));
    const money = n => Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const sel = document.getElementById('spSupplier'), amount = document.getElementById('spAmount'), body = document.getElementById('spAlloc');
    const reg = document.getElementById('spRegister'), regField = document.getElementById('spRegisterField'), warn = document.getElementById('spWarn');
    const sum = document.getElementById('spSum'), submit = document.getElementById('spSubmit');
    let lastSupplier = null;

    function method() { return document.querySelector('input[name="payment_method"]:checked').value; }

    function render() {
        const s = data[sel.value];
        if (sel.value !== lastSupplier) {
            if (lastSupplier !== null || !amount.value) amount.value = s.due.toFixed(2);
            amount.max = s.due;
            lastSupplier = sel.value;
        }
        let left = Math.min(parseFloat(amount.value) || 0, s.due);
        body.innerHTML = '';
        s.open.forEach(inv => {
            const part = Math.max(0, Math.min(left, inv.remaining));
            left = Math.round((left - part) * 100) / 100;
            const tr = document.createElement('tr');
            if (!part) tr.className = 'is-skip';
            tr.innerHTML = `<td>${inv.number}</td><td class="muted num">${inv.date ?? ''}</td><td class="n">${money(inv.remaining)}</td><td class="n">${part ? '<b>' + money(part) + '</b>' : '—'}</td>`;
            body.appendChild(tr);
        });

        const total = Math.min(parseFloat(amount.value) || 0, s.due);
        sum.textContent = money(total) + ' ₪';

        const cash = method() === 'cash';
        regField.hidden = !cash;
        reg.required = cash;
        const balance = parseFloat(reg.selectedOptions[0]?.dataset.balance ?? 0);
        const msgs = [];
        if ((parseFloat(amount.value) || 0) > s.due) msgs.push('المبلغ أكبر من المستحق (' + money(s.due) + ' ₪).');
        if (cash && total > balance) msgs.push('رصيد الخزينة (' + money(balance) + ' ₪) لا يكفي. اختر خزينة أخرى أو حوّل إليها أولاً.');
        warn.hidden = !msgs.length;
        warn.textContent = msgs.join(' ');
        submit.disabled = msgs.length > 0 || total <= 0;

        document.querySelectorAll('.sp-row').forEach(r => r.classList.toggle('is-on', r.dataset.id === sel.value));
    }

    sel.addEventListener('change', render);
    amount.addEventListener('input', render);
    reg.addEventListener('change', render);
    document.querySelectorAll('input[name="payment_method"]').forEach(r => r.addEventListener('change', render));
    document.querySelectorAll('.sp-row').forEach(r => r.addEventListener('click', () => { sel.value = r.dataset.id; render(); amount.focus(); }));
    document.getElementById('spForm').addEventListener('submit', e => {
        const s = sel.selectedOptions[0].textContent.split(' — ')[0];
        if (!confirm('دفع ' + sum.textContent + ' للمورد ' + s + '؟')) e.preventDefault();
    });
    render();
})();
</script>
@endif
@endsection
