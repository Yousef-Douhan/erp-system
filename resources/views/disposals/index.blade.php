@extends('layouts.app')

@section('title', 'سجل الإتلاف')

@section('content')
<div class="x-page">
    <div class="x-head">
        <div>
            <h2>سجل الإتلاف</h2>
            <p>الكميات التي خرجت من المخزون لأنها منتهية أو تالفة، وتكلفتها. للإتلاف افتح «الدفعات» واضغط «إتلاف» بجانب الدفعة.</p>
        </div>
        <div class="x-actions">
            <a href="{{ route('batches.index', ['status' => 'expired']) }}" class="x-btn">الدفعات المنتهية</a>
            <button type="button" class="x-btn is-ghost" onclick="window.print()">طباعة / PDF</button>
        </div>
    </div>

    @include('partials.x-flash')

    <div class="x-stats">
        <div class="x-stat"><span>عمليات إتلاف</span><strong class="num">{{ $totals->cnt }}</strong></div>
        <div class="x-stat"><span>الكمية</span><strong class="num">{{ (int) $totals->qty }}</strong></div>
        <div class="x-stat is-bad"><span>الخسارة بسعر التكلفة</span><strong class="num">{{ number_format($totals->cost, 2) }}<small>₪</small></strong></div>
    </div>

    <section class="erp-card">
        <form method="GET" class="x-body is-top x-fields no-print">
            <div class="x-field"><label for="from">من تاريخ</label><input type="date" id="from" name="from" value="{{ $from->toDateString() }}"></div>
            <div class="x-field"><label for="to">إلى تاريخ</label><input type="date" id="to" name="to" value="{{ $to->toDateString() }}"></div>
            <div class="x-field">
                <label for="reason">السبب</label>
                <select id="reason" name="reason">
                    <option value="">الكل</option>
                    @foreach ($reasons as $k => $l)
                        <option value="{{ $k }}" @selected(request('reason') === $k)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div class="x-actions"><button class="x-btn">عرض</button></div>
        </form>

        @if ($disposals->isEmpty())
            <div class="x-empty">لا يوجد إتلاف في هذه الفترة.</div>
        @else
            <div class="x-table-wrap" style="margin-top:12px">
                <table class="x-table">
                    <thead><tr><th>التاريخ</th><th>الصنف</th><th>الدفعة</th><th>الفرع</th><th>السبب</th><th class="n">الكمية</th><th class="n">التكلفة</th><th>بواسطة</th></tr></thead>
                    <tbody>
                        @foreach ($disposals as $d)
                            <tr>
                                <td class="muted">{{ $d->created_at->format('Y-m-d H:i') }}</td>
                                <td><strong>{{ $d->product->name ?? '—' }}</strong>@if ($d->notes)<div class="muted">{{ $d->notes }}</div>@endif</td>
                                <td class="num">{{ $d->batch->batch_number ?? '—' }}@if ($d->batch?->expiry_date)<div class="muted">{{ $d->batch->expiry_date }}</div>@endif</td>
                                <td class="muted">{{ $d->branch->name ?? '—' }}</td>
                                <td><span class="x-badge {{ $d->reason === 'expired' ? 'is-warn' : 'is-bad' }}">{{ $d->reasonLabel() }}</span></td>
                                <td class="n">{{ $d->quantity }}</td>
                                <td class="n" style="font-weight:600">{{ number_format($d->total_cost, 2) }}</td>
                                <td class="muted">{{ $d->user->username ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="x-pages">{{ $disposals->links() }}</div>
        @endif
    </section>
</div>
@endsection
