@extends('layouts.app')

@section('title', 'فواتير البيع بدون إنترنت')

@section('content')
<div class="x-page">
    <div class="x-head">
        <div>
            <h2>فواتير البيع بدون إنترنت</h2>
            <p>فواتير بيعت وقت انقطاع النت ورُفعت تلقائياً لما رجع. الفواتير «بحاجة مراجعة» حُفظت كاملة، لكن صار فيها شيء لازم تتأكد منه: سعر تغيّر، مخزون ناقص، دين فوق الحد، أو وصفة.</p>
        </div>
    </div>

    @include('partials.x-flash')

    <section class="erp-card">
        <div class="x-body is-top no-print">
            <nav class="x-tabs" aria-label="نوع الفواتير">
                <a href="{{ route('offline-sales.index') }}" class="{{ $tab === 'review' ? 'is-active' : '' }}">بحاجة مراجعة <span class="num">({{ $pendingCount }})</span></a>
                <a href="{{ route('offline-sales.index', ['tab' => 'all']) }}" class="{{ $tab === 'all' ? 'is-active' : '' }}">كل فواتير بدون إنترنت</a>
            </nav>
        </div>

        @if ($sales->isEmpty())
            <div class="x-empty">{{ $tab === 'review' ? 'لا توجد فواتير بحاجة مراجعة.' : 'لم تُسجَّل أي فاتورة بدون إنترنت بعد.' }}</div>
        @else
            <div class="x-table-wrap">
                <table class="x-table">
                    <thead>
                        <tr>
                            <th>الفاتورة</th>
                            <th>وقت البيع</th>
                            <th>وقت الرفع</th>
                            <th>الكاشير</th>
                            <th>العميل</th>
                            <th class="n">الإجمالي</th>
                            <th>الملاحظات</th>
                            <th class="no-print"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sales as $sale)
                            <tr>
                                <td>
                                    @if (Route::has('sales.show'))
                                        <a href="{{ route('sales.show', $sale) }}" class="num" style="font-weight:700">{{ $sale->invoice_number }}</a>
                                    @else
                                        <span class="num">{{ $sale->invoice_number }}</span>
                                    @endif
                                    @if ($sale->offline_number)<div class="muted num">المؤقت: {{ $sale->offline_number }}</div>@endif
                                </td>
                                <td class="num">{{ $sale->offline_at?->format('Y-m-d H:i') }}</td>
                                <td class="num">{{ $sale->updated_at?->format('Y-m-d H:i') }}</td>
                                <td>{{ $sale->employee->username ?? '—' }}@if ($sale->branch)<div class="muted">{{ $sale->branch->name }}</div>@endif</td>
                                <td>{{ $sale->customer_name }}</td>
                                <td class="n num">{{ number_format((float) $sale->total, 2) }}</td>
                                <td style="min-width:260px">
                                    @if ($sale->needs_review)
                                        @foreach (preg_split("/\n+/", (string) $sale->review_note) as $line)
                                            @if (trim($line) !== '')<div style="margin-bottom:3px">• {{ $line }}</div>@endif
                                        @endforeach
                                        @if ($sale->reviewed_at)
                                            <span class="x-badge is-good">روجعت — {{ $sale->reviewer->username ?? '' }} {{ $sale->reviewed_at->format('Y-m-d') }}</span>
                                        @endif
                                    @else
                                        <span class="x-badge is-good">سليمة</span>
                                    @endif
                                </td>
                                <td class="no-print">
                                    @if ($sale->needs_review && !$sale->reviewed_at)
                                        <form method="POST" action="{{ route('offline-sales.reviewed', $sale) }}" style="display:grid;gap:6px;min-width:180px">
                                            @csrf
                                            <input type="text" name="note" maxlength="500" placeholder="ملاحظة (اختياري)" aria-label="ملاحظة المراجعة">
                                            <button type="submit" class="x-btn is-sm">تمت المراجعة</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="x-body">{{ $sales->links() }}</div>
        @endif
    </section>
</div>
@endsection
