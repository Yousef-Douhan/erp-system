@extends('layouts.app')

@section('title', 'مرتجعات المشتريات')

@section('content')
<div class="x-page">
    <div class="x-head">
        <div>
            <h2>مرتجعات المشتريات (للمورد)</h2>
            <p>لتسجيل مرتجع جديد افتح فاتورة الشراء من «فواتير الشراء» واضغط «مرتجع للمورد».</p>
        </div>
        <div class="x-actions">
            <a href="{{ route('purchases.index') }}" class="x-btn">اختيار فاتورة شراء</a>
        </div>
    </div>

    @include('partials.x-flash')

    <div class="x-stats">
        <div class="x-stat"><span>مرتجعات هذا الشهر</span><strong class="num">{{ $totals->cnt }}</strong></div>
        <div class="x-stat is-warn"><span>قيمتها</span><strong class="num">{{ number_format($totals->total, 2) }}<small>₪</small></strong></div>
        <div class="x-stat is-good"><span>استرددناه نقداً</span><strong class="num">{{ number_format($totals->cash, 2) }}<small>₪</small></strong></div>
    </div>

    <section class="erp-card">
        <form method="GET" class="x-body is-top x-fields no-print">
            <div class="x-field"><label for="q">بحث</label><input type="search" id="q" name="q" value="{{ request('q') }}" placeholder="رقم المرتجع، الفاتورة، أو المورد"></div>
            <div class="x-field"><label for="from">من تاريخ</label><input type="date" id="from" name="from" value="{{ request('from') }}"></div>
            <div class="x-field"><label for="to">إلى تاريخ</label><input type="date" id="to" name="to" value="{{ request('to') }}"></div>
            <div class="x-actions"><button class="x-btn">عرض</button>@if (request()->hasAny(['q', 'from', 'to']))<a href="{{ route('purchase-returns.index') }}" class="x-btn is-ghost">مسح</a>@endif</div>
        </form>

        @if ($returns->isEmpty())
            <div class="x-empty">لا توجد مرتجعات للموردين{{ request()->hasAny(['q', 'from', 'to']) ? ' مطابقة' : ' بعد' }}.</div>
        @else
            <div class="x-table-wrap" style="margin-top:12px">
                <table class="x-table">
                    <thead>
                        <tr><th>رقم المرتجع</th><th>فاتورة الشراء</th><th>المورد</th><th>التاريخ</th><th>بواسطة</th><th class="n">الأصناف</th><th class="n">القيمة</th><th class="n">خُصم من المستحق</th><th class="n">نقداً</th><th class="n">رصيد لنا</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($returns as $r)
                            <tr>
                                <td><a href="{{ route('purchase-returns.show', $r) }}" class="num">{{ $r->return_number }}</a></td>
                                <td><a href="{{ route('purchases.index', ['show' => $r->purchase_id]) }}" class="num">{{ $r->purchase->invoice_number ?? '—' }}</a></td>
                                <td>{{ $r->supplier->name ?? '—' }}</td>
                                <td class="muted">{{ $r->created_at->format('Y-m-d H:i') }}</td>
                                <td class="muted">{{ $r->user->username ?? '—' }}</td>
                                <td class="n">{{ $r->items_count }}</td>
                                <td class="n" style="font-weight:600">{{ number_format($r->total, 2) }}</td>
                                <td class="n">{{ number_format($r->debt_reduction, 2) }}</td>
                                <td class="n">{{ number_format($r->cash_refund, 2) }}</td>
                                <td class="n">{{ number_format($r->supplier_credit, 2) }}</td>
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
