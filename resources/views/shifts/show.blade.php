@extends('layouts.app')

@section('title', 'تقرير الوردية #' . $shift->id)

@section('content')
@php $m = fn ($n) => number_format((float) $n, 2); $d = (float) $shift->difference; @endphp

<div class="x-page" style="max-width:900px">
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
        <div class="erp-card-head"><h2>حركة الخزينة خلال الوردية</h2></div>
        @include('shifts._summary', ['summary' => $summary])
        @if ($shift->notes)
            <div class="x-body" style="padding-top:12px"><div class="x-note" style="white-space:pre-line"><b>ملاحظات:</b> {{ $shift->notes }}</div></div>
        @endif
    </section>

    @if ($shift->isOpen() && (auth()->id() === $shift->user_id || auth()->user()->can('manage_accounting')))
        <section class="erp-card no-print">
            <div class="erp-card-head"><h2>إغلاق الوردية</h2></div>
            <form method="POST" action="{{ route('shifts.close', $shift) }}" class="x-body x-fields" onsubmit="return confirm('تأكيد إغلاق الوردية؟')">
                @csrf
                <div class="x-field"><label for="actual">المبلغ الموجود فعلاً</label><input type="number" step="0.01" min="0" name="actual_balance" id="actual" required></div>
                <div class="x-field"><label for="notes">ملاحظات</label><input type="text" name="notes" id="notes" maxlength="500"></div>
                <div class="x-actions"><button class="x-btn">إغلاق</button></div>
            </form>
        </section>
    @endif
</div>
@endsection
