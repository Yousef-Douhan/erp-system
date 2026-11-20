{{-- ملخص الوردية: $summary (من ShiftService::summary) --}}
@php $m = fn ($n) => number_format((float) $n, 2); $mine = $summary['mine'] ?? null; @endphp

@if ($mine)
    @if (($mine['cash_elsewhere'] ?? 0) > 0)
        <div class="x-body is-top"><div class="x-note" style="border-color:var(--red);color:var(--red)">
            <b>تنبيه:</b> {{ $m($mine['cash_elsewhere']) }} ₪ من مبيعات الكاشير النقدية دخلت <b>خزينة أخرى</b> غير خزينة الوردية،
            فلن تظهر في «المفروض أن يكون في الصندوق» هنا. اختر الخزينة الصحيحة عند البيع (من الآن النظام يفرضها تلقائياً).
        </div></div>
    @endif
    <div class="x-body is-top">
        <div class="x-stats" style="margin:0">
            <div class="x-stat is-main"><span>مبيعات الكاشير</span><strong class="num">{{ $m($mine['net']) }}<small>₪</small></strong><em>{{ $mine['count'] }} فاتورة · متوسط {{ $m($mine['avg']) }} ₪</em></div>
            <div class="x-stat is-good"><span>نقداً</span><strong class="num">{{ $m($mine['cash']) }}<small>₪</small></strong></div>
            <div class="x-stat"><span>بنك / محفظة</span><strong class="num">{{ $m($mine['noncash']) }}<small>₪</small></strong></div>
            <div class="x-stat {{ $mine['credit'] > 0 ? 'is-bad' : '' }}"><span>آجل (دين على عملاء)</span><strong class="num">{{ $m($mine['credit']) }}<small>₪</small></strong></div>
            <div class="x-stat {{ $mine['returns_count'] ? 'is-warn' : '' }}"><span>مرتجعات سجّلها</span><strong class="num">{{ $m($mine['returns_total']) }}<small>₪</small></strong><em>{{ $mine['returns_count'] }} مرتجع · خصومات {{ $m($mine['discount']) }} ₪</em></div>
        </div>
    </div>
@endif

<div class="x-table-wrap">
    <table class="x-table">
        <tbody>
            <tr><td>رصيد الخزينة عند الفتح</td><td class="n">{{ $m($summary['opening']) }}</td></tr>
            <tr><td>+ مبيعات نقدية دخلت الصندوق</td><td class="n" style="color:var(--green)">{{ $m($summary['cash_sales']) }}</td></tr>
            @if (($summary['debts'] ?? 0) != 0)
                <tr><td>+ تحصيل ديون ودفعات على فواتير</td><td class="n" style="color:var(--green)">{{ $m($summary['debts']) }}</td></tr>
            @endif
            <tr><td>− المردود نقداً للزبائن</td><td class="n" style="color:var(--red)">{{ $m($summary['refunds']) }}</td></tr>
            <tr><td>− المصروفات</td><td class="n" style="color:var(--red)">{{ $m($summary['expenses']) }}</td></tr>
            <tr><td>− دفعات للموردين</td><td class="n" style="color:var(--red)">{{ $m($summary['supplier_payments']) }}</td></tr>
            @if ($summary['other'] != 0)
                <tr><td>± حركات أخرى <span class="muted">(سندات، إيداع/سحب، تحويلات)</span></td><td class="n">{{ $m($summary['other']) }}</td></tr>
            @endif
        </tbody>
        <tfoot><tr><td>المفروض أن يكون في الصندوق</td><td class="n">{{ $m($summary['expected']) }}</td></tr></tfoot>
    </table>
</div>
@if ($mine && $mine['noncash'] > 0)
    <div class="x-body"><div class="x-note">
        مدفوعات بنك / محفظة في هذه الوردية: <b>{{ $m($mine['noncash']) }} ₪</b>. هذه في الحساب البنكي وليست في الدرج، <b>لا تعدّها</b> عند الإغلاق.
    </div></div>
@endif
