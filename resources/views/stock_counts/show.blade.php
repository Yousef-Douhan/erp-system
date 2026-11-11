@extends('layouts.app')

@section('title', 'الجرد ' . $stockCount->count_number)

@section('content')
@php $draft = $stockCount->isDraft(); @endphp

<div class="x-page">
    <div class="x-head">
        <div>
            <h2>ورقة الجرد <span class="num">{{ $stockCount->count_number }}</span></h2>
            <p>{{ $stockCount->scope }}، أنشأها {{ $stockCount->user->username ?? '—' }} في {{ $stockCount->created_at->format('Y-m-d H:i') }}.
                @if (!$draft) رُحّلت {{ $stockCount->posted_at?->format('Y-m-d H:i') }} بواسطة {{ $stockCount->poster->username ?? '—' }}. @endif</p>
        </div>
        <div class="x-actions">
            <button type="button" class="x-btn is-ghost" onclick="window.print()">طباعة ورقة الجرد</button>
            <a href="{{ route('stock-counts.index') }}" class="x-btn is-ghost">كل الجرد</a>
        </div>
    </div>

    @include('partials.x-flash')

    <div class="x-stats">
        <div class="x-stat"><span>الأصناف</span><strong class="num">{{ $stats['total'] }}</strong></div>
        <div class="x-stat"><span>تم عدّها</span><strong class="num">{{ $stats['counted'] }}</strong><em>المتبقي {{ $stats['total'] - $stats['counted'] }}</em></div>
        <div class="x-stat is-bad"><span>بها عجز</span><strong class="num">{{ $stats['shortage'] }}</strong></div>
        <div class="x-stat is-warn"><span>بها زيادة</span><strong class="num">{{ $stats['surplus'] }}</strong></div>
        <div class="x-stat {{ $stats['value'] < 0 ? 'is-bad' : '' }}"><span>قيمة الفروقات (بسعر الشراء)</span><strong class="num">{{ number_format($stats['value'], 2) }}<small>₪</small></strong></div>
    </div>

    @if ($draft)
        <div class="x-note no-print">الفرق يُحسب على الرصيد الحالي، فإذا بِيع صنف أثناء الجرد يتعدل الفرق تلقائياً. اترك الخانة فارغة للأصناف التي لم تعدّها؛ لن تتغير.</div>
    @endif

    <form method="POST" action="{{ route('stock-counts.update', $stockCount) }}" class="erp-card" id="countForm">
        @csrf
        @method('PUT')
        <div class="erp-card-head no-print">
            <label style="position:relative;flex:1;max-width:360px">
                <span style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)">بحث</span>
                <input type="search" id="countSearch" placeholder="ابحث باسم الصنف أو الباركود" style="width:100%;padding:0 12px">
            </label>
            <label class="x-check"><input type="checkbox" id="onlyDiff"> الفروقات فقط</label>
        </div>
        <div class="x-table-wrap">
            <table class="x-table">
                <thead>
                    <tr><th>الصنف</th><th class="n">{{ $draft ? 'رصيد النظام الآن' : 'رصيد النظام' }}</th><th style="text-align:center">الكمية الفعلية</th><th class="n">الفرق</th><th class="n">قيمة الفرق</th></tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php $it = $row['item']; $diff = $row['diff']; @endphp
                        <tr data-name="{{ mb_strtolower(($it->product->name ?? '') . ' ' . ($it->product->barcode ?? '')) }}" data-diff="{{ $diff ?? '' }}">
                            <td>{{ $it->product->name ?? '—' }} @if ($it->product?->barcode)<div class="muted num">{{ $it->product->barcode }}</div>@endif</td>
                            <td class="n">{{ $row['current'] }}</td>
                            <td style="text-align:center">
                                @if ($draft)
                                    <input type="number" min="0" step="1" name="counted[{{ $it->id }}]" value="{{ $it->counted_qty }}" class="x-qty cnt-input" data-initial="{{ $it->counted_qty }}" data-current="{{ $row['current'] }}" data-cost="{{ $it->unit_cost }}" inputmode="numeric" aria-label="الكمية الفعلية لـ {{ $it->product->name ?? '' }}">
                                @else
                                    {{ $it->counted_qty ?? '—' }}
                                @endif
                            </td>
                            <td class="n cnt-diff">
                                @if ($diff === null) <span class="muted">—</span>
                                @elseif ($diff == 0) <span class="x-badge is-good">مطابق</span>
                                @else <span class="x-badge num {{ $diff > 0 ? 'is-warn' : 'is-bad' }}">{{ $diff > 0 ? '+' : '' }}{{ $diff }}</span>
                                @endif
                            </td>
                            <td class="n cnt-value">{{ $diff ? number_format($row['value'], 2) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($draft)
            <div class="x-body is-top x-actions no-print" style="justify-content:space-between">
                <div class="x-actions">
                    <button class="x-btn is-ghost" name="action" value="save">حفظ ومتابعة لاحقاً</button>
                    @can('edit_products')
                        <button class="x-btn" name="action" value="post" onclick="return confirm('ترحيل الجرد سيضبط أرصدة الأصناف المعدودة على الكميات الفعلية. متابعة؟')">حفظ وترحيل الجرد</button>
                    @endcan
                </div>
            </div>
        @endif
    </form>

    @if ($draft)
        <form method="POST" action="{{ route('stock-counts.destroy', $stockCount) }}" class="no-print" onsubmit="return confirm('حذف مسودة الجرد؟')">
            @csrf
            @method('DELETE')
            <button class="x-btn is-danger is-sm">حذف المسودة</button>
        </form>
    @endif
</div>

<script>
(function () {
    const rows = [...document.querySelectorAll('#countForm tbody tr')];
    const search = document.getElementById('countSearch'), only = document.getElementById('onlyDiff');
    const norm = s => String(s || '').toLowerCase().replace(/[إأآ]/g, 'ا').replace(/ى/g, 'ي').replace(/ة/g, 'ه').trim();
    const money = n => n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function filter() {
        const q = norm(search.value);
        rows.forEach(r => {
            const d = r.dataset.diff;
            r.hidden = (q && !norm(r.dataset.name).includes(q)) || (only.checked && (d === '' || d === '0'));
        });
    }
    search.addEventListener('input', filter);
    only.addEventListener('change', filter);

    document.querySelectorAll('.cnt-input').forEach(i => i.addEventListener('input', () => {
        const r = i.closest('tr'), cell = r.querySelector('.cnt-diff'), val = r.querySelector('.cnt-value');
        if (i.value === '') { r.dataset.diff = ''; cell.innerHTML = '<span class="muted">—</span>'; val.textContent = '—'; return; }
        const d = parseInt(i.value, 10) - parseInt(i.dataset.current, 10);
        r.dataset.diff = d;
        cell.innerHTML = d === 0 ? '<span class="x-badge is-good">مطابق</span>' : `<span class="x-badge num ${d > 0 ? 'is-warn' : 'is-bad'}">${d > 0 ? '+' : ''}${d}</span>`;
        val.textContent = d === 0 ? '—' : money(d * parseFloat(i.dataset.cost || 0));
    }));

    // تُرسل الخانات التي تغيّرت فقط (حتى لا نتجاوز حد عدد الحقول في PHP مع الجرد الكبير)
    document.getElementById('countForm').addEventListener('submit', () => {
        document.querySelectorAll('.cnt-input').forEach(i => { if (i.value === (i.dataset.initial || '')) i.disabled = true; });
    });

    // Enter ينقل للخانة التالية بدل إرسال النموذج (أسرع عند العدّ)
    document.getElementById('countForm').addEventListener('keydown', e => {
        if (e.key === 'Enter' && e.target.classList.contains('cnt-input')) {
            e.preventDefault();
            const all = [...document.querySelectorAll('.cnt-input')].filter(x => !x.closest('tr').hidden);
            const next = all[all.indexOf(e.target) + 1];
            if (next) { next.focus(); next.select(); }
        }
    });
})();
</script>
@endsection
