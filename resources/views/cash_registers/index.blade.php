@extends('layouts.app')

@section('title', 'الخزائن')

@section('content')
@php $m = fn ($n) => number_format((float) $n, 2); @endphp

<style>
    .cr-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 14px; }
    .cr-card { background: var(--paper); border: 1px solid var(--line); border-radius: var(--radius); box-shadow: var(--shadow); display: flex; flex-direction: column; }
    .cr-card.is-off { opacity: .6; }
    .cr-top { padding: 16px 16px 12px; display: flex; justify-content: space-between; gap: 10px; align-items: flex-start; }
    .cr-top h3 { margin: 0; font-size: 16px; }
    .cr-top p { margin: 2px 0 0; color: var(--muted); font-size: 13px; }
    .cr-balance { padding: 0 16px; font-size: 28px; font-weight: 700; color: var(--ink); line-height: 1.2; }
    .cr-balance small { font-size: 14px; color: var(--muted); font-weight: 500; }
    .cr-balance.is-neg { color: var(--red); }
    .cr-today { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; padding: 12px 16px; }
    .cr-today div { background: var(--canvas); border-radius: var(--radius-sm); padding: 8px 10px; font-size: 12.5px; color: var(--muted); }
    .cr-today b { display: block; font-size: 15px; margin-top: 2px; }
    .cr-today .in b { color: var(--green); }
    .cr-today .out b { color: var(--red); }
    .cr-shift { margin: 0 16px; font-size: 13px; padding: 8px 10px; border-radius: var(--radius-sm); background: var(--teal-soft); color: var(--teal-strong); }
    .cr-shift.is-none { background: var(--canvas); color: var(--muted); }
    .cr-actions { display: flex; flex-wrap: wrap; gap: 6px; padding: 12px 16px 16px; margin-top: auto; border-top: 1px solid var(--line-soft); }
    .cr-steps { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; padding: 0 18px 18px; }
    .cr-steps div { border: 1px dashed var(--teal-line); border-radius: var(--radius-sm); padding: 10px 12px; font-size: 13.5px; color: var(--ink-3); }
    .cr-steps b { display: block; color: var(--teal-strong); margin-bottom: 2px; }
</style>

<div class="x-page">
    <div class="x-head">
        <div>
            <h2>الخزائن</h2>
            <p>كل خزينة هي مكان فيه نقد حقيقي: درج الكاشير، أو خزنة المدير. الرصيد هنا يجب أن يساوي المال الموجود فيها فعلاً، وكل قرش يدخل أو يخرج له حركة مسجلة.</p>
        </div>
        <div class="x-actions no-print">
            @can('pay_suppliers')
                <a href="{{ route('supplier-payments.index') }}" class="x-btn is-light">سداد الموردين</a>
            @endcan
            <button type="button" class="x-btn" id="crAddBtn">+ إضافة خزينة</button>
        </div>
    </div>

    @include('partials.x-flash')

    <div class="x-stats">
        <div class="x-stat is-main"><span>النقد في كل الخزائن</span><strong>{{ $m($totals['balance']) }} <small>₪</small></strong></div>
        <div class="x-stat is-good"><span>دخل اليوم</span><strong>{{ $m($totals['in']) }} <small>₪</small></strong></div>
        <div class="x-stat is-bad"><span>خرج اليوم</span><strong>{{ $m($totals['out']) }} <small>₪</small></strong></div>
        <div class="x-stat"><span>ورديات مفتوحة الآن</span><strong>{{ $totals['open'] }}</strong></div>
    </div>

    @if ($registers->isEmpty())
        <section class="erp-card">
            <div class="x-empty">لا توجد خزائن بعد. اضغط «إضافة خزينة» وأضف أول خزينة (مثلاً «درج الكاشير 1»).</div>
        </section>
    @else
        <div class="cr-grid">
            @foreach ($registers as $r)
                @php
                    $t = $today[$r->id] ?? null;
                    $off = $r->status !== 'active';
                @endphp
                <article class="cr-card {{ $off ? 'is-off' : '' }}">
                    <div class="cr-top">
                        <div>
                            <h3>{{ $r->name }}</h3>
                            <p>{{ $r->branch->name ?? '—' }}</p>
                        </div>
                        <span class="x-badge {{ $off ? 'is-bad' : 'is-good' }}">{{ $off ? 'موقوفة' : 'نشطة' }}</span>
                    </div>

                    <div class="cr-balance num {{ (float) $r->current_balance < 0 ? 'is-neg' : '' }}">{{ $m($r->current_balance) }} <small>₪</small></div>

                    <div class="cr-today">
                        <div class="in">دخل اليوم<b class="num">{{ $m($t->cash_in ?? 0) }}</b></div>
                        <div class="out">خرج اليوم<b class="num">{{ $m($t->cash_out ?? 0) }}</b></div>
                    </div>

                    @if ($r->openShift)
                        <div class="cr-shift">وردية مفتوحة: {{ $r->openShift->user->username ?? '—' }}، منذ {{ $r->openShift->opened_at?->format('H:i') }}</div>
                    @else
                        <div class="cr-shift is-none">لا توجد وردية مفتوحة</div>
                    @endif

                    <div class="cr-actions">
                        <a href="{{ route('cash-registers.show', $r) }}" class="x-btn is-sm">كشف الحركات</a>
                        @unless ($off)
                            <button type="button" class="x-btn is-light is-sm" data-cash="deposit" data-id="{{ $r->id }}" data-name="{{ $r->name }}" data-balance="{{ (float) $r->current_balance }}" data-url="{{ route('cash-registers.deposit', $r) }}">إيداع</button>
                            <button type="button" class="x-btn is-light is-sm" data-cash="withdraw" data-id="{{ $r->id }}" data-name="{{ $r->name }}" data-balance="{{ (float) $r->current_balance }}" data-url="{{ route('cash-registers.withdraw', $r) }}">سحب</button>
                            <button type="button" class="x-btn is-light is-sm" data-cash="transfer" data-id="{{ $r->id }}" data-name="{{ $r->name }}" data-balance="{{ (float) $r->current_balance }}">تحويل</button>
                            @can('pay_suppliers')
                                <a href="{{ route('supplier-payments.index', ['register' => $r->id]) }}" class="x-btn is-light is-sm">دفع لمورد</a>
                            @endcan
                        @endunless
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    <section class="erp-card">
        <div class="erp-card-head"><h2>كيف تعمل الخزائن؟</h2></div>
        <div class="cr-steps">
            <div><b>1. بداية الدوام</b>الكاشير يفتح «وردية» على درجه من صفحة الورديات.</div>
            <div><b>2. أثناء اليوم</b>المبيعات النقدية تدخل الدرج تلقائياً، والمرتجعات والمصروفات تخرج منه.</div>
            <div><b>3. آخر الدوام</b>الكاشير يعدّ المال ويغلق الوردية، والنظام يسجل أي زيادة أو عجز.</div>
            <div><b>4. التسليم</b>«تحويل» الإيراد من الدرج إلى خزنة المدير، فيبقى الدرج بالفكة فقط.</div>
        </div>
    </section>

</div>

@include('cash_registers._dialogs', ['transferTargets' => $registers->where('status', 'active')])

<dialog class="cr-dialog" id="crAddDialog">
    <form method="POST" action="{{ route('cash-registers.store') }}">
        @csrf
        <h3>إضافة خزينة</h3>
        <p class="cr-sub">أضف خزينة لكل مكان فيه نقد: درج لكل كاشير، وخزنة للمدير.</p>
        <div class="x-field">
            <label for="crName">الاسم</label>
            <input type="text" name="name" id="crName" required maxlength="255" placeholder="مثال: درج الكاشير 1، خزنة المدير" value="{{ old('name') }}">
        </div>
        <div class="x-field">
            <label for="crBranch">الفرع</label>
            <select name="branch_id" id="crBranch" required>
                @foreach ($branches as $b)
                    <option value="{{ $b->id }}" @selected(old('branch_id') == $b->id)>{{ $b->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="x-field">
            <label for="crOpening">المبلغ الموجود فيها الآن (₪)</label>
            <input type="number" name="opening_balance" id="crOpening" step="0.01" min="0" value="{{ old('opening_balance', 0) }}" inputmode="decimal">
        </div>
        <div class="x-actions">
            <button class="x-btn">إضافة</button>
            <button type="button" class="x-btn is-ghost" data-close>إلغاء</button>
        </div>
    </form>
</dialog>
<script>
(function () {
    const d = document.getElementById('crAddDialog');
    document.getElementById('crAddBtn').addEventListener('click', () => { d.showModal(); document.getElementById('crName').focus(); });
    d.querySelector('[data-close]').addEventListener('click', () => d.close());
    @if ($registers->isEmpty() || ($errors->any() && old('name') !== null))
        d.showModal();
    @endif
})();
</script>
@endsection
