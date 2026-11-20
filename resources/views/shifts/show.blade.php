@extends('layouts.app')

@section('title', 'تقرير الوردية #' . $shift->id)

@section('content')
@php $m = fn ($n) => number_format((float) $n, 2); $d = (float) $shift->difference; @endphp

<div class="x-page" style="max-width:1100px">
    <div class="x-head">
        <div>
            <h2>تقرير الوردية <span class="num">#{{ $shift->id }}</span></h2>
            <p>خزينة «{{ $shift->cashRegister->name ?? '—' }}»، الكاشير {{ $shift->user->username ?? '—' }}.
               من {{ $shift->opened_at->format('Y-m-d H:i') }} {{ $shift->closed_at ? 'إلى ' . $shift->closed_at->format('Y-m-d H:i') : '(ما زالت مفتوحة)' }}</p>
        </div>
        <div class="x-actions">
            <button type="button" class="x-btn is-ghost" onclick="window.print()">طباعة / PDF</button>
            <a href="{{ route('shifts.index') }}" class="x-btn is-ghost">الورديات</a>
        </div>
    </div>

    @include('partials.x-flash')

    @unless ($shift->isOpen())
        <div class="x-stats">
            <div class="x-stat"><span>المتوقع في الصندوق</span><strong class="num">{{ $m($shift->expected_balance) }}<small>₪</small></strong></div>
            <div class="x-stat"><span>الفعلي (المعدود)</span><strong class="num">{{ $m($shift->actual_balance) }}<small>₪</small></strong></div>
            <div class="x-stat {{ $d == 0 ? 'is-good' : ($d > 0 ? 'is-warn' : 'is-bad') }}">
                <span>{{ $d == 0 ? 'مطابق' : ($d > 0 ? 'زيادة' : 'عجز') }}</span>
                <strong class="num">{{ $m(abs($d)) }}<small>₪</small></strong>
                <em>أغلقها {{ $shift->closer->username ?? '—' }}</em>
            </div>
        </div>
    @endunless

    <section class="erp-card">
        <div class="erp-card-head"><h2>ملخص الوردية — {{ $shift->user->username ?? '' }}</h2></div>
        @include('shifts._summary', ['summary' => $summary])
        @if ($shift->notes)
            <div class="x-body" style="padding-top:12px"><div class="x-note" style="white-space:pre-line"><b>ملاحظات:</b> {{ $shift->notes }}</div></div>
        @endif
    </section>

    <section class="erp-card">
        <div class="erp-card-head"><h2>فواتير {{ $shift->user->username ?? 'الكاشير' }} في هذه الوردية</h2><span class="muted">{{ $invoices->total() }} فاتورة</span></div>
        @if ($invoices->isEmpty())
            <div class="x-empty">لم يبع شيئاً في هذه الوردية.</div>
        @else
            <div class="x-table-wrap">
                <table class="x-table">
                    <thead><tr><th>الفاتورة</th><th>الوقت</th><th>العميل</th><th>الدفع</th><th class="n">الإجمالي</th><th class="n">المرتجع</th><th class="n">المدفوع</th><th>الحالة</th></tr></thead>
                    <tbody>
                        @foreach ($invoices as $sale)
                            <tr>
                                <td><a href="{{ route('sales.show', $sale) }}" class="num">{{ $sale->invoice_number }}</a></td>
                                <td class="muted">{{ $sale->created_at->format('H:i') }}</td>
                                <td>{{ $sale->customer->name ?? $sale->customer_name ?? 'زبون نقدي' }}</td>
                                <td>{{ $methodNames[$sale->payment_method] ?? $sale->payment_method }}</td>
                                <td class="n">{{ $m($sale->total) }}</td>
                                <td class="n">{{ (float) $sale->returned_amount > 0 ? $m($sale->returned_amount) : '—' }}</td>
                                <td class="n" style="font-weight:600">{{ $m($sale->paid_amount) }}</td>
                                <td><span class="x-badge {{ $sale->status === 'paid' ? 'is-good' : ($sale->status === 'partial' ? 'is-warn' : 'is-bad') }}">{{ $sale->status === 'paid' ? 'مدفوعة' : ($sale->status === 'partial' ? 'جزئي' : 'آجل') }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="x-pages no-print">{{ $invoices->links() }}</div>
        @endif
    </section>

    @if ($byMethod->isNotEmpty() || $topProducts->isNotEmpty())
    <div style="display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(320px,1fr))">
        <section class="erp-card">
            <div class="erp-card-head"><h2>حسب طريقة الدفع</h2></div>
            <div class="x-table-wrap">
                <table class="x-table">
                    <thead><tr><th>الطريقة</th><th class="n">فواتير</th><th class="n">الصافي</th><th class="n">المقبوض</th></tr></thead>
                    <tbody>
                        @foreach ($byMethod as $row)
                            <tr><td>{{ $methodNames[$row->payment_method] ?? $row->payment_method }}</td><td class="n">{{ $row->cnt }}</td><td class="n">{{ $m($row->net) }}</td><td class="n">{{ $m($row->paid) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        <section class="erp-card">
            <div class="erp-card-head"><h2>أكثر ما باعه</h2></div>
            <div class="x-table-wrap">
                <table class="x-table">
                    <thead><tr><th>الصنف</th><th class="n">الكمية</th><th class="n">القيمة</th></tr></thead>
                    <tbody>
                        @foreach ($topProducts as $p)
                            <tr><td>{{ $p->name }}</td><td class="n">{{ rtrim(rtrim(number_format((float) $p->qty, 2, '.', ''), '0'), '.') }}</td><td class="n">{{ $m($p->total) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
    @endif

    @if ($movements->isNotEmpty())
    <section class="erp-card">
        <div class="erp-card-head"><h2>كل حركات الصندوق في الوردية</h2></div>
        <div class="x-table-wrap">
            <table class="x-table">
                <thead><tr><th>الوقت</th><th>الحركة</th><th>البيان</th><th>بواسطة</th><th class="n">داخل</th><th class="n">خارج</th><th class="n">الرصيد بعدها</th></tr></thead>
                <tbody>
                    @foreach ($movements as $mv)
                        <tr>
                            <td class="muted">{{ $mv->created_at->format('H:i') }}</td>
                            <td>@if ($url = $mv->referenceUrl())<a href="{{ $url }}">{{ $mv->typeLabel() }}</a>@else{{ $mv->typeLabel() }}@endif</td>
                            <td class="muted">{{ $mv->description }}</td>
                            <td class="muted">{{ $mv->user->username ?? '—' }}</td>
                            <td class="n" style="color:var(--green)">{{ $mv->isIn() ? $m($mv->amount) : '' }}</td>
                            <td class="n" style="color:var(--red)">{{ $mv->isIn() ? '' : $m($mv->amount) }}</td>
                            <td class="n">{{ $m($mv->balance_after) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
    @endif

    @if ($shift->isOpen() && (auth()->id() === $shift->user_id || auth()->user()->can('manage_accounting')))
        <section class="erp-card no-print">
            <div class="erp-card-head"><h2>إغلاق الوردية</h2></div>
            <form method="POST" action="{{ route('shifts.close', $shift) }}" class="x-body x-fields js-shift-close" onsubmit="return confirm('تأكيد إغلاق الوردية؟')">
                @csrf
                <div class="x-field"><label for="actual">المبلغ الموجود فعلاً</label><input type="number" step="0.01" min="0" name="actual_balance" id="actual" required></div>
                <div class="x-field"><label for="notes">ملاحظات</label><input type="text" name="notes" id="notes" maxlength="500"></div>
                <label class="x-check" style="font-size:13px"><input type="checkbox" name="confirm_cash" value="1"> أؤكد أن المبلغ نقدي في الدرج</label>
                <div class="x-actions"><button class="x-btn">إغلاق</button></div>
            </form>
            @include('shifts._offline_guard', ['shiftUserId' => $shift->user_id])
        </section>
    @endif
</div>
@endsection
