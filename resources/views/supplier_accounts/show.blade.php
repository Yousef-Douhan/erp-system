@extends('layouts.app')

@section('title', 'كشف ' . $supplier->name)

@section('content')
@php $m = fn ($n) => number_format((float) $n, 2); @endphp

<div class="x-page">
    <div class="x-head">
        <div>
            <h2>كشف حساب: {{ $supplier->name }}</h2>
            <p>{{ $supplier->company_name ?: 'كل فاتورة شراء تزيد ما علينا للمورد، وكل دفعة تنقصه.' }}</p>
        </div>
        <div class="x-actions no-print">
            <a href="{{ route('suppliers.index') }}" class="x-btn is-ghost">الموردون</a>
            @can('pay_suppliers')
                @if ((float) $supplier->balance > 0)
                    <a href="{{ route('supplier-payments.index', ['supplier' => $supplier->id]) }}" class="x-btn">دفعة للمورد</a>
                @endif
            @endcan
            <button type="button" class="x-btn is-ghost" onclick="window.print()">طباعة</button>
        </div>
    </div>

    @include('partials.x-flash')

    <div class="x-stats">
        <div class="x-stat"><span>مجموع الفواتير</span><strong>{{ $m($totals->invoices) }} <small>₪</small></strong></div>
        <div class="x-stat is-good"><span>مجموع المدفوع</span><strong>{{ $m($totals->payments) }} <small>₪</small></strong></div>
        @if ((float) $totals->returns > 0)
        <div class="x-stat is-warn"><span>مرتجعات له</span><strong>{{ $m($totals->returns) }} <small>₪</small></strong></div>
        @endif
        <div class="x-stat is-main"><span>المستحق للمورد الآن</span><strong>{{ $m($supplier->balance) }} <small>₪</small></strong>
            <em>{{ (float) $supplier->balance < 0 ? 'رصيد لنا عند المورد يُخصم من فواتيره القادمة' : $openInvoices->count() . ' فاتورة غير مسددة' }}</em></div>
    </div>

    <section class="erp-card">
        @if ($statement->isEmpty())
            <div class="x-empty">لا توجد حركات على هذا المورد بعد.</div>
        @else
            <div class="x-table-wrap">
                <table class="x-table">
                    <thead><tr><th>التاريخ</th><th>الحركة</th><th class="n">فاتورة (له)</th><th class="n">دفعة (منه)</th><th class="n">الرصيد بعدها</th></tr></thead>
                    <tbody>
                        @foreach ($statement as $row)
                            <tr>
                                <td class="muted num">{{ $row->created_at?->format('Y-m-d H:i') }}</td>
                                <td><span class="x-badge {{ (float) $row->credit > 0 ? 'is-warn' : 'is-good' }}">{{ \App\Services\SupplierLedger::label($row->transaction_type) }}</span></td>
                                <td class="n">{{ (float) $row->credit > 0 ? $m($row->credit) : '' }}</td>
                                <td class="n">{{ (float) $row->debit > 0 ? $m($row->debit) : '' }}</td>
                                <td class="n"><strong>{{ $m($row->balance) }}</strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="x-pages no-print">{{ $statement->links() }}</div>
        @endif
    </section>
</div>
@endsection
