@extends('layouts.app')

@section('title', 'اقتراح طلبية شراء')

@section('content')
@php $q = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.'); @endphp

<div class="x-page">
    <div class="x-head">
        <div>
            <h2>اقتراح طلبية شراء @if ($branchName)<span class="muted" style="font-size:15px">— {{ $branchName }}</span>@endif</h2>
            <p>الأصناف التي وصلت للحد الأدنى أو ستنفد خلال أسبوع حسب مبيعات آخر 30 يوماً. الكمية المقترحة تكفي {{ $cover }} يوماً. هذه قائمة للطلب فقط — البضاعة تدخل المخزون عند تسجيل فاتورة الشراء.</p>
        </div>
        <div class="x-actions no-print">
            <button type="button" class="x-btn is-ghost" onclick="window.print()">طباعة / PDF</button>
            @can('create_purchases')
                <a href="{{ route('purchases.index') }}" class="x-btn">تسجيل فاتورة شراء</a>
            @endcan
        </div>
    </div>

    <form method="GET" class="erp-card x-body is-top x-fields no-print">
        @if (!\App\Support\Tenancy::branchId() && $branches->count() > 1)
            <div class="x-field">
                <label for="branch">الفرع</label>
                <select id="branch" name="branch_id" onchange="this.form.submit()">
                    @foreach ($branches as $b)
                        <option value="{{ $b->id }}" @selected($branchId === $b->id)>{{ $b->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="x-field">
            <label for="cover">تكفي لمدة</label>
            <select id="cover" name="cover" onchange="this.form.submit()">
                @foreach ([14, 30, 45, 60] as $d)
                    <option value="{{ $d }}" @selected($cover === $d)>{{ $d }} يوماً</option>
                @endforeach
            </select>
        </div>
    </form>

    <div class="x-stats">
        <div class="x-stat is-warn"><span>أصناف تحتاج طلب</span><strong class="num">{{ $lines->count() }}</strong></div>
        <div class="x-stat"><span>عدد الموردين</span><strong class="num">{{ $bySupplier->count() }}</strong></div>
        <div class="x-stat is-main"><span>القيمة التقديرية</span><strong class="num">{{ number_format($totalValue, 2) }}<small>₪</small></strong><em>بآخر سعر شراء</em></div>
    </div>

    @if ($lines->isEmpty())
        <section class="erp-card"><div class="x-empty">ممتاز، لا يوجد صنف يحتاج طلباً الآن.</div></section>
    @else
        @foreach ($bySupplier as $supplier => $items)
            @php
                $text = "طلبية" . ($branchName ? " — {$branchName}" : '') . "\n" . $items->map(fn ($i) => "• {$i['name']}: {$i['suggest']}")->implode("\n");
            @endphp
            <section class="erp-card">
                <div class="erp-card-head">
                    <h2>{{ $supplier }} <span class="muted" style="font-weight:500">({{ $items->count() }} صنف · {{ number_format($items->sum('value'), 2) }} ₪)</span></h2>
                    <button type="button" class="x-btn is-ghost is-sm no-print js-copy" data-text="{{ $text }}">نسخ للواتساب</button>
                </div>
                <div class="x-table-wrap">
                    <table class="x-table">
                        <thead><tr><th>الصنف</th><th class="n">الرصيد</th><th class="n">الحد الأدنى</th><th class="n">يُباع يومياً</th><th>يكفي</th><th class="n">الكمية المقترحة</th><th class="n">آخر سعر</th><th class="n">القيمة</th></tr></thead>
                        <tbody>
                            @foreach ($items as $i)
                                <tr>
                                    <td><strong>{{ $i['name'] }}</strong>@if ($i['barcode'])<div class="muted num">{{ $i['barcode'] }}</div>@endif</td>
                                    <td class="n" style="color:{{ $i['stock'] <= 0 ? 'var(--red)' : 'inherit' }};font-weight:700">{{ $i['stock'] }}</td>
                                    <td class="n">{{ $i['alert'] }}</td>
                                    <td class="n">{{ $i['daily'] > 0 ? $q($i['daily']) : '—' }}</td>
                                    <td>
                                        @if ($i['stock'] <= 0)
                                            <span class="x-badge is-bad">نفد</span>
                                        @elseif ($i['days_left'] !== null)
                                            <span class="x-badge {{ $i['days_left'] < 3 ? 'is-bad' : 'is-warn' }}">{{ $i['days_left'] }} يوم</span>
                                        @else
                                            <span class="x-badge is-warn">تحت الحد</span>
                                        @endif
                                    </td>
                                    <td class="n" style="font-weight:800;font-size:15px">{{ $i['suggest'] }}</td>
                                    <td class="n">{{ number_format($i['cost'], 2) }}</td>
                                    <td class="n">{{ number_format($i['value'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    @endif
</div>

<script>
document.querySelectorAll('.js-copy').forEach(b => b.addEventListener('click', async () => {
    try { await navigator.clipboard.writeText(b.dataset.text); b.textContent = 'تم النسخ ✓'; }
    catch (e) { prompt('انسخ النص:', b.dataset.text); }
    setTimeout(() => b.textContent = 'نسخ للواتساب', 2000);
}));
</script>
@endsection
