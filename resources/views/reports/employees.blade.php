@extends('layouts.app')

@section('title', 'مبيعات الموظفين')

@section('content')
@php
    $m = fn ($n) => number_format((float) $n, 2);
    $max = max(1, (float) $employees->max('net'));
@endphp

<div class="x-page">
    @include('reports._nav', ['subtitle' => 'أداء كل موظف في البيع، مع المرتجعات والخصومات ومجمل الربح.'])

    <section class="erp-card">
        @if ($employees->isEmpty())
            <div class="x-empty">لا توجد مبيعات في هذه الفترة.</div>
        @else
            <div class="x-table-wrap">
                <table class="x-table">
                    <thead><tr><th>الموظف</th><th class="n">الفواتير</th><th class="n">إجمالي المبيعات</th><th class="n">المرتجعات</th><th class="n">الصافي</th><th class="n">المحصّل</th><th class="n">الخصومات</th><th class="n">متوسط الفاتورة</th><th class="n">مجمل الربح</th></tr></thead>
                    <tbody>
                        @foreach ($employees as $e)
                            <tr>
                                <td style="min-width:160px">
                                    <strong>{{ $e['name'] }}</strong>
                                    <div style="height:5px;border-radius:5px;background:var(--canvas);margin-top:6px;overflow:hidden"><i style="display:block;height:100%;width:{{ round($e['net'] / $max * 100) }}%;background:var(--teal)"></i></div>
                                </td>
                                <td class="n">{{ $e['count'] }}</td>
                                <td class="n">{{ $m($e['gross']) }}</td>
                                <td class="n" style="{{ $e['returned'] > 0 ? 'color:var(--amber)' : '' }}">{{ $m($e['returned']) }}</td>
                                <td class="n" style="font-weight:700">{{ $m($e['net']) }}</td>
                                <td class="n">{{ $m($e['paid']) }}</td>
                                <td class="n">{{ $m($e['discount']) }}</td>
                                <td class="n">{{ $m($e['avg']) }}</td>
                                <td class="n" style="color:{{ $e['profit'] >= 0 ? 'var(--green)' : 'var(--red)' }}">{{ $m($e['profit']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>الإجمالي</td>
                            <td class="n">{{ $employees->sum('count') }}</td>
                            <td class="n">{{ $m($employees->sum('gross')) }}</td>
                            <td class="n">{{ $m($employees->sum('returned')) }}</td>
                            <td class="n">{{ $m($employees->sum('net')) }}</td>
                            <td class="n">{{ $m($employees->sum('paid')) }}</td>
                            <td class="n">{{ $m($employees->sum('discount')) }}</td>
                            <td class="n">—</td>
                            <td class="n">{{ $m($employees->sum('profit')) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </section>
</div>
@endsection
