@extends('layouts.app')

@section('title', 'الحركات المخزنية')

@section('content')
@php $q = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.'); @endphp

<div class="x-page">
    <div class="x-head">
        <div>
            <h2>الحركات المخزنية</h2>
            <p>سجل كل كمية دخلت أو خرجت من المخزون ومن أين جاءت. الحركات تُسجَّل تلقائياً من البيع والمرتجعات وفواتير الشراء والنقل والجرد، ولا تُعدَّل يدوياً.</p>
        </div>
        <div class="x-actions no-print">
            @can('stock_count')
                <a href="{{ route('stock-counts.index') }}" class="x-btn is-light">الكمية غير صحيحة؟ اعمل جرداً</a>
            @endcan
        </div>
    </div>

    <section class="erp-card">
        <form method="GET" class="x-body is-top x-fields no-print">
            <div class="x-field">
                <label for="product_id">الصنف</label>
                <select name="product_id" id="product_id">
                    <option value="">كل الأصناف</option>
                    @foreach ($products as $p)
                        <option value="{{ $p->id }}" @selected(request('product_id') == $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="x-field">
                <label for="type">نوع الحركة</label>
                <select name="type" id="type">
                    <option value="">كل الأنواع</option>
                    @foreach ($types as $key => $label)
                        <option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @if ($warehouses->count() > 1)
                <div class="x-field">
                    <label for="warehouse_id">المستودع</label>
                    <select name="warehouse_id" id="warehouse_id">
                        <option value="">كل المستودعات</option>
                        @foreach ($warehouses as $w)
                            <option value="{{ $w->id }}" @selected(request('warehouse_id') == $w->id)>{{ $w->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="x-field"><label for="from">من</label><input type="date" name="from" id="from" value="{{ request('from') }}"></div>
            <div class="x-field"><label for="to">إلى</label><input type="date" name="to" id="to" value="{{ request('to') }}"></div>
            <div class="x-actions">
                <button class="x-btn">عرض</button>
                @if (request()->hasAny(['product_id', 'type', 'warehouse_id', 'from', 'to']))
                    <a href="{{ route('inventory-transactions.index') }}" class="x-btn is-ghost">مسح</a>
                @endif
            </div>
        </form>

        @if ($transactions->isEmpty())
            <div class="x-empty">لا توجد حركات مطابقة.</div>
        @else
            <div class="x-table-wrap">
                <table class="x-table">
                    <thead>
                        <tr><th>التاريخ</th><th>الصنف</th><th>الحركة</th><th>المستودع</th><th class="n">وارد</th><th class="n">صادر</th><th class="n">الرصيد بعدها</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($transactions as $t)
                            @php $in = (float) $t->qty_in > 0; @endphp
                            <tr>
                                <td class="muted num">{{ $t->created_at?->format('Y-m-d H:i') }}</td>
                                <td><strong>{{ $t->product->name ?? '—' }}</strong></td>
                                <td><span class="x-badge {{ $in ? 'is-good' : 'is-bad' }}">{{ $types[$t->type] ?? $t->type }}</span></td>
                                <td class="muted">{{ $t->warehouse->name ?? '—' }}</td>
                                <td class="n">{{ $in ? $q($t->qty_in) : '' }}</td>
                                <td class="n">{{ (float) $t->qty_out > 0 ? $q($t->qty_out) : '' }}</td>
                                <td class="n"><strong>{{ $t->balance_after !== null ? $q($t->balance_after) : '—' }}</strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="x-pages no-print">{{ $transactions->links() }}</div>
        @endif
    </section>
</div>
@endsection
