@extends('layouts.app')

@section('title', 'المرتجعات')

@section('content')
<div class="x-page">
    <div class="x-head">
        <div>
            <h2>مرتجعات المبيعات</h2>
            <p>لتسجيل مرتجع جديد افتح الفاتورة من «الفواتير» واضغط «مرتجع».</p>
        </div>
        <div class="x-actions">
            <a href="{{ route('sales.index') }}" class="x-btn">اختيار فاتورة للإرجاع</a>
        </div>
    </div>

    @include('partials.x-flash')

    <div class="x-stats">
        <div class="x-stat"><span>مرتجعات هذا الشهر</span><strong class="num">{{ $totals->cnt }}</strong></div>
        <div class="x-stat is-warn"><span>قيمتها</span><strong class="num">{{ number_format($totals->total, 2) }}<small>₪</small></strong></div>
        <div class="x-stat"><span>رُد نقداً</span><strong class="num">{{ number_format($totals->refund, 2) }}<small>₪</small></strong></div>
    </div>

    <section class="erp-card">
        <form method="GET" class="x-body is-top x-fields no-print">
            <div class="x-field"><label for="q">بحث</label><input type="search" id="q" name="q" value="{{ request('q') }}" placeholder="رقم المرتجع أو الفاتورة"></div>
            <div class="x-field"><label for="from">من تاريخ</label><input type="date" id="from" name="from" value="{{ request('from') }}"></div>
            <div class="x-field"><label for="to">إلى تاريخ</label><input type="date" id="to" name="to" value="{{ request('to') }}"></div>
            <div class="x-actions"><button class="x-btn">عرض</button>@if (request()->hasAny(['q', 'from', 'to']))<a href="{{ route('returns.index') }}" class="x-btn is-ghost">مسح</a>@endif</div>
        </form>

        @if ($returns->isEmpty())
            <div class="x-empty">لا توجد مرتجعات{{ request()->hasAny(['q', 'from', 'to']) ? ' مطابقة' : ' بعد' }}.</div>
        @else
            <div class="x-table-wrap" style="margin-top:12px">
                <table class="x-table">
                    <thead>
                        <tr><th>رقم المرتجع</th><th>الفاتورة</th><th>العميل</th><th>التاريخ</th><th>بواسطة</th><th class="n">الأصناف</th><th class="n">القيمة</th><th class="n">رُد نقداً</th><th>المخزون</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($returns as $r)
                            <tr>
                                <td><a href="{{ route('returns.show', $r) }}" class="num">{{ $r->return_number }}</a></td>
                                <td><a href="{{ route('sales.show', $r->sale_id) }}" class="num">{{ $r->sale->invoice_number ?? '—' }}</a></td>
                                <td>{{ $r->customer->name ?? 'عميل نقدي' }}</td>
                                <td class="muted">{{ $r->created_at->format('Y-m-d H:i') }}</td>
                                <td class="muted">{{ $r->user->username ?? '—' }}</td>
                                <td class="n">{{ $r->items_count }}</td>
                                <td class="n" style="font-weight:600">{{ number_format($r->total, 2) }}</td>
                                <td class="n">{{ number_format($r->refund_amount, 2) }}</td>
                                <td><span class="x-badge {{ $r->restocked ? 'is-good' : 'is-warn' }}">{{ $r->restocked ? 'أُعيد' : 'تالف' }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="x-pages">{{ $returns->links() }}</div>
        @endif
    </section>
</div>
@endsection
