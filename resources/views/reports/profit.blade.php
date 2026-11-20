@extends('layouts.app')

@section('title', 'تقرير الأرباح')

@section('content')
@php
    $m = fn ($n) => number_format((float) $n, 2);
    $q = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
    $losing = $byProduct->filter(fn ($p) => $p['profit'] < 0);
@endphp

<div class="x-page">
    @include('reports._nav', ['subtitle' => 'الربح = صافي المبيعات (بعد الخصم والمرتجعات) − تكلفة الأصناف المباعة.'])

    <div class="x-stats">
        <div class="x-stat"><span>صافي المبيعات</span><strong class="num">{{ $m($summary['revenue']) }}<small>₪</small></strong><em>{{ $summary['count'] }} فاتورة{{ $summary['returned'] > 0 ? '، مرتجعات ' . $m($summary['returned']) : '' }}</em></div>
        <div class="x-stat"><span>تكلفة البضاعة المباعة</span><strong class="num">{{ $m($summary['cogs']) }}<small>₪</small></strong></div>
        <div class="x-stat {{ $summary['gross'] >= 0 ? 'is-good' : 'is-bad' }}"><span>مجمل الربح</span><strong class="num">{{ $m($summary['gross']) }}<small>₪</small></strong><em>هامش {{ $summary['margin'] }}%</em></div>
        <div class="x-stat"><span>المصروفات</span><strong class="num">{{ $m($summary['expenses']) }}<small>₪</small></strong></div>
        @if ($summary['disposals'] > 0)
            <div class="x-stat is-bad"><span>خسائر الإتلاف</span><strong class="num">{{ $m($summary['disposals']) }}<small>₪</small></strong><em>منتهي الصلاحية / تالف</em></div>
        @endif
        <div class="x-stat is-main"><span>صافي الربح</span><strong class="num">{{ $m($summary['net']) }}<small>₪</small></strong><em>بعد المصروفات{{ $summary['disposals'] > 0 ? ' والإتلاف' : '' }}</em></div>
    </div>

    <section class="erp-card">
        <div class="erp-card-head">
            <h2>يوماً بيوم</h2>
            <a href="{{ request()->fullUrlWithQuery(['export' => 'csv', 'section' => 'daily']) }}" class="no-print">تصدير</a>
        </div>
        @if ($daily->isEmpty())
            <div class="x-empty">لا توجد مبيعات أو مصروفات في هذه الفترة.</div>
        @else
            <div class="x-table-wrap">
                <table class="x-table">
                    <thead><tr><th>اليوم</th><th class="n">الفواتير</th><th class="n">صافي المبيعات</th><th class="n">التكلفة</th><th class="n">مجمل الربح</th><th class="n">المصروفات</th>@if ($summary['disposals'] > 0)<th class="n">الإتلاف</th>@endif<th class="n">صافي الربح</th></tr></thead>
                    <tbody>
                        @foreach ($daily as $d)
                            <tr>
                                <td class="num">{{ $d['day'] }}</td>
                                <td class="n">{{ $d['count'] }}</td>
                                <td class="n">{{ $m($d['revenue']) }}</td>
                                <td class="n">{{ $m($d['cogs']) }}</td>
                                <td class="n" style="color:{{ $d['gross'] >= 0 ? 'var(--green)' : 'var(--red)' }}">{{ $m($d['gross']) }}</td>
                                <td class="n">{{ $m($d['expenses']) }}</td>
                                @if ($summary['disposals'] > 0)<td class="n">{{ $m($d['disposals']) }}</td>@endif
                                <td class="n" style="font-weight:700;color:{{ $d['net'] >= 0 ? 'var(--ink)' : 'var(--red)' }}">{{ $m($d['net']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>الإجمالي</td><td class="n">{{ $summary['count'] }}</td><td class="n">{{ $m($summary['revenue']) }}</td><td class="n">{{ $m($summary['cogs']) }}</td>
                            <td class="n">{{ $m($summary['gross']) }}</td><td class="n">{{ $m($summary['expenses']) }}</td>@if ($summary['disposals'] > 0)<td class="n">{{ $m($summary['disposals']) }}</td>@endif<td class="n">{{ $m($summary['net']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </section>

    <section class="erp-card">
        <div class="erp-card-head">
            <h2>الربح حسب الصنف</h2>
            <a href="{{ request()->fullUrlWithQuery(['export' => 'csv', 'section' => 'products']) }}" class="no-print">تصدير</a>
        </div>
        @if ($losing->isNotEmpty())
            <div class="x-body" style="padding-bottom:12px"><div class="x-note is-warn">{{ $losing->count() }} صنف بِيع بأقل من تكلفته في هذه الفترة. السبب الأكثر شيوعاً: سعر الشراء مُدخل للعلبة بدل الحبة. راجع سعر شراء الصنف أو شغّل الأمر <code>php artisan products:fix-costs</code>.</div></div>
        @endif
        @if ($byProduct->isEmpty())
            <div class="x-empty">لا توجد مبيعات في هذه الفترة.</div>
        @else
            <div class="x-table-wrap">
                <table class="x-table">
                    <thead><tr><th>#</th><th>الصنف</th><th class="n">الكمية</th><th class="n">الإيراد</th><th class="n">التكلفة</th><th class="n">الربح</th><th class="n">الهامش</th></tr></thead>
                    <tbody>
                        @foreach ($byProduct as $i => $p)
                            <tr>
                                <td class="muted num">{{ $i + 1 }}</td>
                                <td>{{ $p['name'] }}</td>
                                <td class="n">{{ $q($p['qty']) }}</td>
                                <td class="n">{{ $m($p['revenue']) }}</td>
                                <td class="n">{{ $m($p['cogs']) }}</td>
                                <td class="n" style="font-weight:700;color:{{ $p['profit'] >= 0 ? 'var(--green)' : 'var(--red)' }}">{{ $m($p['profit']) }}</td>
                                <td class="n"><span class="x-badge num {{ $p['margin'] >= 20 ? 'is-good' : ($p['margin'] >= 0 ? 'is-warn' : 'is-bad') }}">{{ $p['margin'] }}%</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="x-body is-top"><small style="color:var(--muted)">إيراد الصنف قبل خصم الفاتورة العام. التكلفة من سعر الدفعة وقت البيع، وللفواتير القديمة من سعر شراء المنتج الحالي.</small></div>
        @endif
    </section>
</div>
@endsection
