@extends('layouts.app')

@section('title', 'التحليلات')

@section('content')
@php
    $change = $yesterdaySales > 0 ? (($todaySales - $yesterdaySales) / $yesterdaySales) * 100 : null;
    $max = max(1, $series->max('total'));
    $days = ['Sat' => 'سبت', 'Sun' => 'أحد', 'Mon' => 'اثنين', 'Tue' => 'ثلاثاء', 'Wed' => 'أربعاء', 'Thu' => 'خميس', 'Fri' => 'جمعة'];
    $statusLabels = ['paid' => 'مدفوعة', 'partial' => 'جزئية', 'unpaid' => 'غير مدفوعة'];
@endphp

<style>
    .dash { display: grid; grid-template-columns: minmax(0, 1fr); gap: 18px; max-width: 1400px; margin: 0 auto; }
    .dash .num { font-variant-numeric: tabular-nums; direction: ltr; unicode-bidi: isolate; }
    .dash-hello { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 12px; }
    .dash-hello h2 { margin: 0; font-size: 22px; font-weight: 700; color: var(--ink); }
    .dash-hello p { margin: 4px 0 0; color: var(--muted); font-size: 14px; }
    .dash-quick { display: flex; flex-wrap: wrap; gap: 8px; }
    .dash-quick a {
        height: 40px; padding: 0 14px; display: inline-flex; align-items: center; gap: 8px; border-radius: 10px;
        border: 1.5px solid var(--line); background: var(--paper); color: var(--ink); font-size: 13.5px; font-weight: 600; text-decoration: none;
    }
    .dash-quick a:hover { border-color: var(--teal); color: var(--teal); background: var(--teal-soft); }
    .dash-quick svg { width: 18px; height: 18px; }

    .dash-top { display: grid; grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr); gap: 18px; }
    .dash-today { background: var(--ink); color: #fff; border-radius: var(--radius); padding: 22px 24px 18px; display: flex; flex-direction: column; gap: 18px; min-height: 260px; }
    .dash-today-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; }
    .dash-today-label { font-size: 14px; color: #A9C2BF; }
    .dash-today-value { font-size: 44px; font-weight: 700; line-height: 1.1; margin-top: 6px; letter-spacing: -.5px; }
    .dash-today-value span { font-size: 20px; font-weight: 500; color: #A9C2BF; margin-inline-start: 6px; }
    .dash-today-meta { font-size: 13.5px; color: #C9D8D6; margin-top: 6px; }
    .dash-delta { display: inline-flex; align-items: center; gap: 4px; padding: 3px 9px; border-radius: 999px; font-size: 12.5px; font-weight: 600; background: rgba(255,255,255,.08); color: #C9D8D6; }
    .dash-delta.up { background: rgba(80, 200, 140, .16); color: #8FE3B6; }
    .dash-delta.down { background: rgba(240, 120, 100, .16); color: #F5B2A6; }
    .dash-month { text-align: left; }
    .dash-month div { font-size: 13px; color: #A9C2BF; }
    .dash-month strong { display: block; font-size: 20px; font-weight: 700; margin-top: 4px; }

    .dash-chart { flex: 1; display: grid; grid-template-columns: repeat(14, minmax(0, 1fr)); gap: 6px; align-items: end; min-height: 110px; }
    .dash-bar { display: flex; flex-direction: column; align-items: center; gap: 6px; height: 100%; justify-content: flex-end; }
    .dash-bar i { display: block; width: 100%; max-width: 26px; border-radius: 6px 6px 2px 2px; background: #2F6563; min-height: 3px; }
    .dash-bar.is-today i { background: #7FD1C8; }
    .dash-bar:hover i { background: #5FB3AE; }
    .dash-bar small { font-size: 10.5px; color: #8FA9A6; white-space: nowrap; }

    .dash-stats { display: grid; grid-template-rows: repeat(3, 1fr); gap: 12px; }
    .dash-stat { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 18px; text-decoration: none; color: inherit; }
    .dash-stat:hover { border-color: var(--teal-line); }
    .dash-stat-label { font-size: 13.5px; color: var(--muted); }
    .dash-stat-value { font-size: 24px; font-weight: 700; color: var(--ink); margin-top: 2px; }
    .dash-stat-value span { font-size: 13px; color: var(--muted); font-weight: 500; margin-inline-start: 4px; }
    .dash-stat-note { font-size: 12.5px; color: var(--muted); margin-top: 2px; }
    .dash-stat-icon { width: 44px; height: 44px; flex: none; border-radius: 12px; display: grid; place-items: center; background: var(--teal-soft); color: var(--teal); }
    .dash-stat-icon.amber { background: var(--amber-soft); color: var(--amber); }
    .dash-stat-icon.red { background: var(--red-soft); color: var(--red); }
    .dash-stat-icon svg { width: 22px; height: 22px; }

    .dash-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; }
    .dash-list { list-style: none; margin: 0; padding: 0 6px 8px; }
    .dash-list li { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 12px; border-radius: 10px; font-size: 13.5px; }
    .dash-list li + li { border-top: 1px solid var(--line-soft); }
    .dash-list .name { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 500; }
    .dash-list .sub { display: block; font-size: 12px; color: var(--muted); font-weight: 400; }
    .dash-pill { flex: none; font-size: 12px; font-weight: 600; padding: 2px 9px; border-radius: 7px; background: var(--canvas); color: var(--ink); }
    .dash-pill.red { background: var(--red-soft); color: var(--red); }
    .dash-pill.amber { background: var(--amber-soft); color: var(--amber); }
    .dash-pill.green { background: var(--green-soft); color: var(--green); }
    .dash-rank { width: 24px; height: 24px; flex: none; border-radius: 7px; display: grid; place-items: center; background: var(--canvas); font-size: 12px; font-weight: 700; color: var(--muted); }
    .dash-meter { height: 6px; border-radius: 6px; background: var(--canvas); margin-top: 6px; overflow: hidden; }
    .dash-meter i { display: block; height: 100%; background: var(--teal); border-radius: 6px; }

    .dash-table { width: 100%; font-size: 13.5px; }
    .dash-table th { text-align: right; padding: 10px 18px !important; }
    .dash-table td { padding: 11px 18px; border-top: 1px solid var(--line-soft); }
    .dash-table a { color: var(--teal); font-weight: 600; text-decoration: none; }
    .dash-table a:hover { text-decoration: underline; }

    @media (max-width: 1200px) { .dash-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .dash-grid > :last-child { grid-column: 1 / -1; } }
    @media (max-width: 900px) {
        .dash-top, .dash-grid { grid-template-columns: minmax(0, 1fr); }
        .dash-today-head { flex-wrap: wrap; }
        .dash-month { text-align: right; }
        .dash-stats { grid-template-rows: none; }
        .dash-today-value { font-size: 36px; }
        .dash-bar small { display: none; }
    }
</style>

<div class="dash">
    <div class="dash-hello">
        <div>
            <h2>التحليلات</h2>
            <p>المبيعات والديون والمخزون بالأرقام، مع رسم آخر 14 يوماً والأصناف الأكثر مبيعاً.</p>
        </div>
        <div class="dash-quick">
            @can('manage_sales')
                <a href="{{ route('pos.index') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>بيع جديد</a>
            @endcan
            @can('view_pharmacy')
                <a href="{{ route('inventory.transactions.stock-in') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8v12H3V8M1 3h22v5H1zM12 11v6m-3-3 3 3 3-3"/></svg>توريد بضاعة</a>
            @endcan
            @can('view_purchases')
                <a href="{{ route('purchases.index') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h2l2.4 12.2a2 2 0 0 0 2 1.8h8.8a2 2 0 0 0 2-1.6L22 7H6"/></svg>فاتورة شراء</a>
            @endcan
        </div>
    </div>

    <div class="dash-top">
        {{-- مبيعات اليوم + الرسم --}}
        <section class="dash-today" aria-label="مبيعات اليوم">
            <div class="dash-today-head">
                <div>
                    <div class="dash-today-label">مبيعات اليوم</div>
                    <div class="dash-today-value num">{{ number_format($todaySales, 2) }}<span>₪</span></div>
                    <div class="dash-today-meta">
                        {{ $todayCount }} {{ $todayCount === 1 ? 'فاتورة' : 'فواتير' }}
                        @if (!is_null($change))
                            <span class="dash-delta {{ $change >= 0 ? 'up' : 'down' }}">
                                <span class="num">{{ $change >= 0 ? '+' : '' }}{{ number_format($change, 0) }}%</span> عن أمس
                            </span>
                        @endif
                    </div>
                </div>
                <div class="dash-month">
                    <div>هذا الشهر</div>
                    <strong class="num">{{ number_format($monthSales, 2) }} ₪</strong>
                    <div>{{ $monthCount }} فاتورة</div>
                </div>
            </div>
            <div class="dash-chart" role="img" aria-label="مبيعات آخر 14 يوماً">
                @foreach ($series as $point)
                    <div class="dash-bar {{ $point['date']->isToday() ? 'is-today' : '' }}" title="{{ $point['date']->format('Y-m-d') }}: {{ number_format($point['total'], 2) }} ₪">
                        <i style="height: {{ max(2, round($point['total'] / $max * 100)) }}%"></i>
                        <small>{{ $point['date']->isToday() ? 'اليوم' : ($days[$point['date']->format('D')] ?? '') }}</small>
                    </div>
                @endforeach
            </div>
        </section>

        <div class="dash-stats">
            <a href="{{ Route::has('debts.index') ? route('debts.index') : '#' }}" class="erp-card dash-stat">
                <div>
                    <div class="dash-stat-label">ديون العملاء</div>
                    <div class="dash-stat-value num">{{ number_format($receivables, 2) }}<span>₪</span></div>
                    <div class="dash-stat-note">على {{ $debtorsCount }} {{ $debtorsCount === 1 ? 'عميل' : 'عملاء' }}</div>
                </div>
                <span class="dash-stat-icon amber"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M12 3v18M17 7H9.5a3 3 0 0 0 0 6h5a3 3 0 0 1 0 6H6"/></svg></span>
            </a>
            <a href="{{ Route::has('cash-registers.index') ? route('cash-registers.index') : '#' }}" class="erp-card dash-stat">
                <div>
                    <div class="dash-stat-label">النقدية في الخزائن</div>
                    <div class="dash-stat-value num">{{ number_format($cashOnHand, 2) }}<span>₪</span></div>
                    <div class="dash-stat-note">{{ $registersCount }} {{ $registersCount === 1 ? 'خزينة نشطة' : 'خزائن نشطة' }}</div>
                </div>
                <span class="dash-stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4h18v16H3zM12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6z"/></svg></span>
            </a>
            <a href="{{ Route::has('products.index') ? route('products.index') : '#' }}" class="erp-card dash-stat">
                <div>
                    <div class="dash-stat-label">تحتاج إعادة طلب</div>
                    <div class="dash-stat-value num">{{ $lowStockCount }}<span>منتج</span></div>
                    <div class="dash-stat-note">
                        @if ($expiredCount)
                            و{{ $expiredCount }} {{ $expiredCount === 1 ? 'دفعة منتهية' : 'دفعات منتهية' }} في المخزون
                        @else
                            وصلت للحد الأدنى أو أقل
                        @endif
                    </div>
                </div>
                <span class="dash-stat-icon {{ $expiredCount ? 'red' : 'amber' }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0zM12 9v4m0 4h.01"/></svg></span>
            </a>
        </div>
    </div>

    <div class="dash-grid">
        <section class="erp-card">
            <div class="erp-card-head">
                <h2>منتجات قاربت على النفاد</h2>
                @if (Route::has('products.index'))<a href="{{ route('products.index') }}">كل المنتجات</a>@endif
            </div>
            @if ($lowStock->isEmpty())
                <div class="erp-empty">كل المنتجات فوق الحد الأدنى.</div>
            @else
                <ul class="dash-list">
                    @foreach ($lowStock as $p)
                        <li>
                            <span class="name">{{ $p->name }}<span class="sub">الحد الأدنى <span class="num">{{ $p->alert_quantity }}</span></span></span>
                            <span class="dash-pill {{ $p->stock_quantity <= 0 ? 'red' : 'amber' }}">{{ $p->stock_quantity <= 0 ? 'نفد' : 'متبقي ' . $p->stock_quantity }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="erp-card">
            <div class="erp-card-head">
                <h2>صلاحية قريبة الانتهاء</h2>
                @if (Route::has('batches.index'))<a href="{{ route('batches.index') }}">كل الدفعات</a>@endif
            </div>
            @if ($expiring->isEmpty())
                <div class="erp-empty">لا توجد دفعات تنتهي خلال 60 يوماً.</div>
            @else
                <ul class="dash-list">
                    @foreach ($expiring as $b)
                        @php $daysLeft = (int) today()->diffInDays(\Illuminate\Support\Carbon::parse($b->expiry_date), false); @endphp
                        <li>
                            <span class="name">{{ $b->product->name ?? '—' }}<span class="sub">دفعة {{ $b->batch_number }}، الكمية <span class="num">{{ $b->quantity }}</span></span></span>
                            <span class="dash-pill {{ $daysLeft < 0 ? 'red' : ($daysLeft <= 30 ? 'amber' : '') }}">
                                {{ $daysLeft < 0 ? 'منتهية' : ($daysLeft === 0 ? 'تنتهي اليوم' : 'بعد ' . $daysLeft . ' يوم') }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="erp-card">
            <div class="erp-card-head">
                <h2>الأكثر مبيعاً هذا الشهر</h2>
            </div>
            @if ($topProducts->isEmpty())
                <div class="erp-empty">لا توجد مبيعات هذا الشهر بعد.</div>
            @else
                @php $topMax = max(1, (float) $topProducts->max('revenue')); @endphp
                <ul class="dash-list">
                    @foreach ($topProducts as $i => $tp)
                        <li>
                            <span class="dash-rank num">{{ $i + 1 }}</span>
                            <span class="name" style="flex:1">
                                {{ $tp->name }}
                                <span class="sub"><span class="num">{{ rtrim(rtrim(number_format($tp->qty, 2, '.', ''), '0'), '.') }}</span> قطعة</span>
                                <span class="dash-meter"><i style="width: {{ round($tp->revenue / $topMax * 100) }}%"></i></span>
                            </span>
                            <span class="num" style="font-weight:600">{{ number_format($tp->revenue, 2) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    <section class="erp-card">
        <div class="erp-card-head">
            <h2>آخر الفواتير</h2>
            @if (Route::has('sales.index'))<a href="{{ route('sales.index') }}">كل الفواتير</a>@endif
        </div>
        @if ($latestSales->isEmpty())
            <div class="erp-empty">لم تُسجَّل أي فاتورة بعد. ابدأ من نقطة البيع.</div>
        @else
            <div style="overflow-x:auto">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>رقم الفاتورة</th>
                            <th>العميل</th>
                            <th>الوقت</th>
                            <th>الإجمالي</th>
                            <th>المدفوع</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($latestSales as $sale)
                            <tr>
                                <td>
                                    @if (Route::has('sales.show'))
                                        <a href="{{ route('sales.show', $sale) }}" class="num">{{ $sale->invoice_number }}</a>
                                    @else
                                        <span class="num">{{ $sale->invoice_number }}</span>
                                    @endif
                                </td>
                                <td>{{ $sale->customer->name ?? $sale->customer_name ?? 'عميل نقدي' }}</td>
                                <td style="color:var(--muted)">{{ $sale->created_at?->locale('ar')->diffForHumans() }}</td>
                                <td class="num" style="font-weight:600">{{ number_format($sale->total, 2) }}</td>
                                <td class="num">{{ number_format($sale->paid_amount, 2) }}</td>
                                <td>
                                    <span class="dash-pill {{ $sale->status === 'paid' ? 'green' : ($sale->status === 'partial' ? 'amber' : 'red') }}">
                                        {{ $statusLabels[$sale->status] ?? $sale->status }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
@endsection
