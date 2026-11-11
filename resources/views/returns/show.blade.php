@extends('layouts.app')

@section('title', 'المرتجع ' . $return->return_number)

@section('content')
@php $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.'); @endphp

<div class="x-page" style="max-width:960px">
    <div class="x-head">
        <div>
            <h2>مرتجع <span class="num">{{ $return->return_number }}</span></h2>
            <p>من الفاتورة <a href="{{ route('sales.show', $return->sale_id) }}" class="num" style="color:var(--teal);font-weight:600">{{ $return->sale->invoice_number ?? '—' }}</a>،
                {{ $return->created_at->format('Y-m-d H:i') }}، بواسطة {{ $return->user->username ?? '—' }}</p>
        </div>
        <div class="x-actions">
            <button type="button" class="x-btn is-ghost" onclick="window.print()">طباعة / PDF</button>
            <a href="{{ route('returns.index') }}" class="x-btn is-ghost">كل المرتجعات</a>
        </div>
    </div>

    @include('partials.x-flash')

    <div class="x-stats">
        <div class="x-stat is-main"><span>قيمة المرتجع</span><strong class="num">{{ number_format($return->total, 2) }}<small>₪</small></strong></div>
        <div class="x-stat"><span>رُد للزبون</span><strong class="num">{{ number_format($return->refund_amount, 2) }}<small>₪</small></strong><em>{{ $return->cashRegister ? 'من ' . $return->cashRegister->name : ((float) $return->refund_amount > 0 ? 'تحويل / محفظة' : '—') }}</em></div>
        <div class="x-stat"><span>خُفّض من دين العميل</span><strong class="num">{{ number_format($return->debt_reduction, 2) }}<small>₪</small></strong><em>{{ $return->customer->name ?? 'عميل نقدي' }}</em></div>
        <div class="x-stat {{ $return->restocked ? 'is-good' : 'is-warn' }}"><span>المخزون</span><strong style="font-size:17px">{{ $return->restocked ? 'أُعيدت للمخزون' : 'تالفة (لم تُعد)' }}</strong></div>
    </div>

    <section class="erp-card">
        <div class="erp-card-head"><h2>الأصناف المرتجعة</h2></div>
        <div class="x-table-wrap">
            <table class="x-table">
                <thead><tr><th>الصنف</th><th class="n">الكمية</th><th class="n">سعر الوحدة</th><th class="n">القيمة</th></tr></thead>
                <tbody>
                    @foreach ($return->items as $it)
                        <tr>
                            <td>{{ $it->product->name ?? '—' }} @if ($it->saleItem?->unit_name)<span class="muted">({{ $it->saleItem->unit_name }})</span>@endif</td>
                            <td class="n">{{ $fmt($it->quantity) }}</td>
                            <td class="n">{{ number_format($it->unit_price, 2) }}</td>
                            <td class="n">{{ number_format($it->total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot><tr><td colspan="3">الإجمالي</td><td class="n">{{ number_format($return->total, 2) }}</td></tr></tfoot>
            </table>
        </div>
        @if ($return->reason)
            <div class="x-body"><div class="x-note"><b>السبب:</b> {{ $return->reason }}</div></div>
        @endif
    </section>
</div>
@endsection
