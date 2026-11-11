<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>إيصال {{ $sale->invoice_number }}</title>
    @php
        $m = fn ($n) => number_format((float) $n, 2);
        $q = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
        $net = \App\Services\SaleService::netTotal($sale);
        $remaining = \App\Services\SaleService::remaining($sale);
        $methods = ['cash' => 'نقدي', 'debt' => 'آجل (دين)'] + \App\Models\PaymentMethod::pluck('name', 'code')->all();
        $itemsSum = (float) $sale->items->sum('total');
    @endphp
    <style>
        @page { size: 80mm auto; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #e9ecef; font-family: 'IBM Plex Sans Arabic', Tahoma, Arial, sans-serif; color: #000; }
        .bar { display: flex; gap: 8px; justify-content: center; padding: 10px; background: #102A2B; }
        .bar button { height: 36px; padding: 0 16px; border: 0; border-radius: 8px; background: #fff; color: #0F5C5A; font: 700 14px inherit; cursor: pointer; }
        .r { width: 80mm; margin: 12px auto; background: #fff; padding: 4mm 4mm 6mm; font-size: 11.5px; line-height: 1.45; }
        .c { text-align: center; }
        h1 { margin: 0; font-size: 16px; }
        .sub { font-size: 11px; }
        hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        .row { display: flex; justify-content: space-between; gap: 8px; }
        .n { direction: ltr; unicode-bidi: isolate; font-variant-numeric: tabular-nums; white-space: nowrap; }
        table { width: 100%; border-collapse: collapse; }
        th { font-size: 10.5px; text-align: right; border-bottom: 1px solid #000; padding: 2px 0; }
        td { padding: 3px 0; vertical-align: top; }
        td.n, th.n { text-align: left; }
        .item-name { font-weight: 700; }
        .total { font-size: 15px; font-weight: 700; }
        .foot { margin-top: 8px; font-size: 10.5px; }
        .r svg { max-width: 100%; height: auto; }
        @media print {
            body { background: #fff; }
            .bar { display: none; }
            .r { margin: 0; width: 100%; padding: 2mm 3mm 6mm; }
        }
    </style>
</head>
<body>
    <div class="bar"><button type="button" onclick="window.print()">طباعة الإيصال</button><button type="button" onclick="window.close()">إغلاق</button></div>

    <div class="r">
        <div class="c">
            <h1>{{ $sale->organization->name ?? config('app.name') }}</h1>
            @if ($sale->branch)<div class="sub">{{ $sale->branch->name }}{{ $sale->branch->phone ? ' — ' . $sale->branch->phone : '' }}</div>@endif
            @if ($sale->branch?->address)<div class="sub">{{ $sale->branch->address }}</div>@endif
        </div>
        <hr>
        <div class="row"><span>فاتورة</span><b class="n">{{ $sale->invoice_number }}</b></div>
        <div class="row"><span>التاريخ</span><span class="n">{{ $sale->created_at->format('Y-m-d H:i') }}</span></div>
        <div class="row"><span>الكاشير</span><span>{{ $sale->employee->username ?? '—' }}</span></div>
        <div class="row"><span>العميل</span><span>{{ $sale->customer->name ?? $sale->customer_name ?? 'عميل نقدي' }}</span></div>
        <hr>
        <table>
            <thead><tr><th>الصنف</th><th class="n">الكمية</th><th class="n">السعر</th><th class="n">المجموع</th></tr></thead>
            <tbody>
                @foreach ($sale->items as $it)
                    <tr><td colspan="4" class="item-name">{{ $it->product->name ?? '—' }}{{ $it->unit_name ? ' (' . $it->unit_name . ')' : '' }}</td></tr>
                    <tr>
                        <td>@if ((float) $it->discount > 0)<span style="font-size:10px">خصم {{ $m($it->discount) }}</span>@endif @if ((float) $it->returned_quantity > 0)<span style="font-size:10px">مرتجع {{ $q($it->returned_quantity) }}</span>@endif</td>
                        <td class="n">{{ $q($it->quantity) }}</td>
                        <td class="n">{{ $m($it->unit_price) }}</td>
                        <td class="n">{{ $m($it->total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <hr>
        @if ((float) $sale->discount > 0)
            <div class="row"><span>المجموع</span><span class="n">{{ $m($itemsSum) }}</span></div>
            <div class="row"><span>خصم الفاتورة</span><span class="n">- {{ $m($sale->discount) }}</span></div>
        @endif
        @if ((float) $sale->returned_amount > 0)
            <div class="row"><span>قبل المرتجعات</span><span class="n">{{ $m($sale->total) }}</span></div>
            <div class="row"><span>المرتجعات</span><span class="n">- {{ $m($sale->returned_amount) }}</span></div>
        @endif
        <div class="row total"><span>الإجمالي</span><span class="n">{{ $m($net) }} ₪</span></div>
        <div class="row"><span>المدفوع ({{ $methods[$sale->payment_method] ?? $sale->payment_method }})</span><span class="n">{{ $m($sale->paid_amount) }}</span></div>
        @if ($remaining > 0)
            <div class="row"><b>المتبقي (دين)</b><b class="n">{{ $m($remaining) }}</b></div>
        @endif
        <hr>
        <div class="c foot">
            شكراً لزيارتكم، نتمنى لكم الشفاء العاجل<br>
            الأدوية المصروفة لا تُسترجع إلا بالفاتورة وخلال مدة الإرجاع
        </div>
        <div class="c" style="margin-top:6px">{!! \App\Support\Barcode::svg($sale->invoice_number, 34, 1.1, false) !!}</div>
    </div>

    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
</body>
</html>
