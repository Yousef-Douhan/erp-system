@extends('layouts.app')

@section('title', 'الأصناف الراكدة')

@section('content')
@php $m = fn ($n) => number_format((float) $n, 2); @endphp

<div class="x-page">
    @include('reports._nav', ['subtitle' => 'أصناف في المخزون لم تُبع منذ ' . $days . ' يوماً أو أكثر، مرتبة حسب قيمتها. فكّر في عرض عليها أو إيقاف طلبها.'])

    <div class="x-stats">
        <div class="x-stat is-warn"><span>أصناف راكدة</span><strong class="num">{{ $totals->cnt }}</strong></div>
        <div class="x-stat"><span>قيمتها بسعر الشراء</span><strong class="num">{{ $m($totals->value) }}<small>₪</small></strong><em>رأس مال مجمّد</em></div>
    </div>

    <section class="erp-card">
        @if ($products->isEmpty())
            <div class="x-empty">ممتاز، كل الأصناف المتوفرة بيعت خلال آخر {{ $days }} يوماً.</div>
        @else
            <div class="x-table-wrap">
                <table class="x-table">
                    <thead><tr><th>الصنف</th><th class="n">الرصيد</th><th class="n">سعر الشراء</th><th class="n">سعر البيع</th><th class="n">قيمة المخزون</th><th>آخر بيع</th></tr></thead>
                    <tbody>
                        @foreach ($products as $p)
                            <tr>
                                <td>{{ $p->name }}</td>
                                <td class="n">{{ $p->stock_quantity }}</td>
                                <td class="n">{{ $m($p->purchase_price) }}</td>
                                <td class="n">{{ $m($p->selling_price) }}</td>
                                <td class="n" style="font-weight:700">{{ $m($p->stock_value) }}</td>
                                <td>
                                    @if ($p->last_sold)
                                        <span class="muted">{{ \Illuminate\Support\Carbon::parse($p->last_sold)->toDateString() }}</span>
                                        <span class="x-badge is-warn">منذ {{ (int) \Illuminate\Support\Carbon::parse($p->last_sold)->diffInDays(now()) }} يوم</span>
                                    @else
                                        <span class="x-badge is-bad">لم يُبع أبداً</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="x-pages">{{ $products->links() }}</div>
        @endif
    </section>
</div>
@endsection
