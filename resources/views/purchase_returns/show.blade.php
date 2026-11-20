@extends('layouts.app')

@section('title', 'مرتجع للمورد ' . $return->return_number)

@section('content')
<div class="x-page" style="max-width:960px">
    <div class="x-head">
        <div>
            <h2>مرتجع للمورد <span class="num">{{ $return->return_number }}</span></h2>
            <p>المورد <b>{{ $return->supplier->name ?? '—' }}</b> — من فاتورة الشراء
                <a href="{{ route('purchases.index', ['show' => $return->purchase_id]) }}" class="num" style="color:var(--teal);font-weight:600">{{ $return->purchase->invoice_number ?? '—' }}</a>،
                {{ $return->created_at->format('Y-m-d H:i') }}، بواسطة {{ $return->user->username ?? '—' }}</p>
        </div>
        <div class="x-actions">
            <button type="button" class="x-btn is-ghost" onclick="window.print()">طباعة / PDF</button>
            <a href="{{ route('purchase-returns.index') }}" class="x-btn is-ghost">كل مرتجعات المشتريات</a>
        </div>
    </div>

    @include('partials.x-flash')

    <div class="x-stats">
        <div class="x-stat is-main"><span>قيمة المرتجع</span><strong class="num">{{ number_format($return->total, 2) }}<small>₪</small></strong></div>
        <div class="x-stat"><span>خُصم من المستحق للمورد</span><strong class="num">{{ number_format($return->debt_reduction, 2) }}<small>₪</small></strong></div>
        <div class="x-stat is-good"><span>استرددناه نقداً</span><strong class="num">{{ number_format($return->cash_refund, 2) }}<small>₪</small></strong><em>{{ $return->cashRegister ? 'إلى ' . $return->cashRegister->name : '—' }}</em></div>
        <div class="x-stat"><span>رصيد لنا عند المورد</span><strong class="num">{{ number_format($return->supplier_credit, 2) }}<small>₪</small></strong></div>
    </div>

    <section class="erp-card">
        <div class="erp-card-head"><h2>الأصناف المرتجعة</h2></div>
        <div class="x-table-wrap">
            <table class="x-table">
                <thead><tr><th>الصنف</th><th>الدفعة</th><th class="n">الكمية</th><th class="n">سعر الشراء</th><th class="n">القيمة</th></tr></thead>
                <tbody>
                    @foreach ($return->items as $it)
                        <tr>
                            <td>{{ $it->product->name ?? '—' }}</td>
                            <td class="muted">{{ $it->batch->batch_number ?? '—' }}@if ($it->batch?->expiry_date) · {{ $it->batch->expiry_date }}@endif</td>
                            <td class="n">{{ $it->quantity }}</td>
                            <td class="n">{{ number_format($it->unit_cost, 2) }}</td>
                            <td class="n">{{ number_format($it->total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot><tr><td colspan="4">الإجمالي</td><td class="n">{{ number_format($return->total, 2) }}</td></tr></tfoot>
            </table>
        </div>
        @if ($return->reason)
            <div class="x-body"><div class="x-note"><b>السبب:</b> {{ $return->reason }}</div></div>
        @endif
    </section>
</div>
@endsection
