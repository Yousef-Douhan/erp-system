@extends('layouts.app')

@section('title', 'تقرير المبيعات')

@section('content')
@php
    $m = fn ($n) => number_format((float) $n, 2);
    $q = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
@endphp

<div class="x-page">
    @include('reports._nav', ['subtitle' => 'المبيعات ' . ($group === 'month' ? 'شهراً بشهر' : 'يوماً بيوم') . ' مع طريقة التحصيل، والأصناف الأكثر مبيعاً.', 'exportParams' => ['section' => 'rows']])

    <div class="x-stats">
        <div class="x-stat is-main"><span>صافي المبيعات</span><strong class="num">{{ $m($summary['net']) }}<small>₪</small></strong><em>{{ $summary['count'] }} فاتورة · متوسط {{ $m($summary['avg']) }} ₪</em></div>
        <div class="x-stat"><span>الخصومات</span><strong class="num">{{ $m($summary['discount']) }}<small>₪</small></strong></div>
        <div class="x-stat is-warn"><span>المرتجعات</span><strong class="num">{{ $m($summary['returned']) }}<small>₪</small></strong></div>
        <div class="x-stat is-good"><span>نقداً</span><strong class="num">{{ $m($summary['cash']) }}<small>₪</small></strong><em>بنك/محفظة {{ $m($summary['noncash']) }} ₪</em></div>
        <div class="x-stat is-bad"><span>بقي آجلاً</span><strong class="num">{{ $m($summary['credit']) }}<small>₪</small></strong></div>
    </div>

    <section class="erp-card">
        <div class="erp-card-head">
            <h2>{{ $group === 'month' ? 'شهراً بشهر' : 'يوماً بيوم' }}</h2>
            <div class="x-actions no-print">
                <nav class="x-tabs" aria-label="التجميع">
                    <a href="{{ request()->fullUrlWithQuery(['group' => 'day', 'export' => null]) }}" class="{{ $group === 'day' ? 'is-active' : '' }}">يومي</a>
                    <a href="{{ request()->fullUrlWithQuery(['group' => 'month', 'export' => null]) }}" class="{{ $group === 'month' ? 'is-active' : '' }}">شهري</a>
                </nav>
            </div>
        </div>
        @if ($rows->isEmpty())
            <div class="x-empty">لا توجد مبيعات في هذه الفترة.</div>
        @else
            <div class="x-table-wrap">
                <table class="x-table">
                    <thead><tr><th>{{ $group === 'month' ? 'الشهر' : 'اليوم' }}</th><th class="n">الفواتير</th><th class="n">قبل الخصم</th><th class="n">الخصم</th><th class="n">المرتجعات</th><th class="n">الصافي</th><th class="n">نقداً</th><th class="n">بنك/محفظة</th><th class="n">آجل</th><th class="n">متوسط الفاتورة</th></tr></thead>
                    <tbody>
                        @foreach ($rows as $r)
                            <tr>
                                <td class="num">{{ $r['period'] }}</td>
                                <td class="n">{{ $r['count'] }}</td>
                                <td class="n">{{ $m($r['gross']) }}</td>
                                <td class="n">{{ $m($r['discount']) }}</td>
                                <td class="n">{{ $m($r['returned']) }}</td>
                                <td class="n" style="font-weight:700">{{ $m($r['net']) }}</td>
                                <td class="n">{{ $m($r['cash']) }}</td>
                                <td class="n">{{ $m($r['noncash']) }}</td>
                                <td class="n" style="color:{{ $r['credit'] > 0 ? 'var(--red)' : 'inherit' }}">{{ $m($r['credit']) }}</td>
                                <td class="n">{{ $m($r['avg']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>الإجمالي</td><td class="n">{{ $summary['count'] }}</td><td class="n">{{ $m($summary['gross']) }}</td><td class="n">{{ $m($summary['discount']) }}</td>
                            <td class="n">{{ $m($summary['returned']) }}</td><td class="n">{{ $m($summary['net']) }}</td><td class="n">{{ $m($summary['cash']) }}</td>
                            <td class="n">{{ $m($summary['noncash']) }}</td><td class="n">{{ $m($summary['credit']) }}</td><td class="n">{{ $m($summary['avg']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </section>

    <section class="erp-card">
        <div class="erp-card-head">
            <h2>الأصناف الأكثر مبيعاً (أعلى 20 بالكمية)</h2>
            <a href="{{ request()->fullUrlWithQuery(['export' => 'csv', 'section' => 'top']) }}" class="no-print">تصدير</a>
        </div>
        @if ($top->isEmpty())
            <div class="x-empty">لا توجد مبيعات في هذه الفترة.</div>
        @else
            <div class="x-table-wrap">
                <table class="x-table">
                    <thead><tr><th>#</th><th>الصنف</th><th class="n">الكمية (حبة)</th><th class="n">قيمة المبيعات</th><th class="n">في كم فاتورة</th></tr></thead>
                    <tbody>
                        @foreach ($top as $i => $p)
                            <tr>
                                <td class="muted">{{ $i + 1 }}</td>
                                <td><strong>{{ $p['name'] }}</strong></td>
                                <td class="n" style="font-weight:700">{{ $q($p['qty']) }}</td>
                                <td class="n">{{ $m($p['revenue']) }}</td>
                                <td class="n">{{ $p['invoices'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
@endsection
