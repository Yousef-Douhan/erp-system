{{-- ملخص حركة الخزينة خلال الوردية: $summary --}}
@php $m = fn ($n) => number_format((float) $n, 2); @endphp
<div class="x-table-wrap">
    <table class="x-table">
        <tbody>
            <tr><td>رصيد الخزينة عند الفتح</td><td class="n">{{ $m($summary['opening']) }}</td></tr>
            <tr><td>+ المقبوض من المبيعات <span class="muted">({{ $summary['sales_count'] }} فاتورة بقيمة {{ $m($summary['sales_total']) }})</span></td><td class="n" style="color:var(--green)">{{ $m($summary['cash_sales']) }}</td></tr>
            <tr><td>− المردود للزبائن <span class="muted">({{ $summary['returns_count'] }} مرتجع)</span></td><td class="n" style="color:var(--red)">{{ $m($summary['refunds']) }}</td></tr>
            <tr><td>− المصروفات</td><td class="n" style="color:var(--red)">{{ $m($summary['expenses']) }}</td></tr>
            <tr><td>− دفعات للموردين</td><td class="n" style="color:var(--red)">{{ $m($summary['supplier_payments']) }}</td></tr>
            <tr><td>± حركات أخرى <span class="muted">(تحصيل ديون، سندات قبض وصرف)</span></td><td class="n">{{ $m($summary['other']) }}</td></tr>
        </tbody>
        <tfoot><tr><td>المفروض أن يكون في الصندوق</td><td class="n">{{ $m($summary['expected']) }}</td></tr></tfoot>
    </table>
</div>
