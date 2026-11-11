<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ملصقات الباركود</title>
    @php
        [$w, $h] = array_map('intval', explode('x', $size));
        $barH = max(18, (int) round($h * 1.1));
    @endphp
    <style>
        @page { size: {{ $w }}mm {{ $h }}mm; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'IBM Plex Sans Arabic', Tahoma, Arial, sans-serif; color: #000; background: #e9ecef; }
        .bar { position: sticky; top: 0; display: flex; gap: 10px; align-items: center; justify-content: center; padding: 10px; background: #102A2B; color: #fff; font-size: 14px; }
        .bar button { height: 36px; padding: 0 16px; border: 0; border-radius: 8px; background: #fff; color: #0F5C5A; font: 700 14px inherit; cursor: pointer; }
        .sheet { display: flex; flex-wrap: wrap; gap: 4mm; padding: 6mm; justify-content: center; }
        .label {
            width: {{ $w }}mm; height: {{ $h }}mm; background: #fff; padding: 1.2mm 1.5mm; overflow: hidden;
            display: flex; flex-direction: column; align-items: center; justify-content: space-between; text-align: center;
            outline: 1px dashed #bbb;
        }
        .name { font-size: {{ $w >= 50 ? 9 : 7.5 }}pt; font-weight: 700; line-height: 1.15; max-height: 2.3em; overflow: hidden; width: 100%; }
        .code svg { width: 100%; height: auto; max-height: {{ $h * 0.55 }}mm; display: block; }
        .code { width: 100%; }
        .price { font-size: {{ $w >= 50 ? 10 : 8 }}pt; font-weight: 700; direction: ltr; }
        @media print {
            body { background: #fff; }
            .bar { display: none; }
            .sheet { display: block; padding: 0; }
            .label { outline: 0; page-break-after: always; break-after: page; }
        }
    </style>
</head>
<body>
    <div class="bar">
        <span>{{ $labels->count() }} ملصق بمقاس {{ $w }}×{{ $h }} مم</span>
        <button type="button" onclick="window.print()">طباعة</button>
    </div>
    <div class="sheet">
        @foreach ($labels as $l)
            <div class="label">
                <div class="name">{{ $l['name'] }}</div>
                <div class="code">{!! \App\Support\Barcode::svg($l['code'], $barH, 1.4) !!}</div>
                @if ($showPrice)<div class="price">{{ number_format($l['price'], 2) }} ₪</div>@endif
            </div>
        @endforeach
    </div>
    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
</body>
</html>
