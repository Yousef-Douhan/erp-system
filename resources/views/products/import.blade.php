@extends('layouts.app')

@section('title', 'استيراد الأدوية من Excel')

@section('content')
@php
    $q = fn ($n) => $n === null ? '—' : rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
    $result = $preview['result'] ?? null;
    $s = $result['summary'] ?? null;
    $valid = $s ? $s['create'] + $s['update'] : 0;
@endphp

<div class="x-page">
    <div class="x-head">
        <div>
            <h2>استيراد الأدوية من Excel</h2>
            <p>بدل إضافة كل صنف لحاله: عبّئ القالب بالأصناف وأسعارها وكمياتها وارفعه. النظام يفحص كل صف ويعرضه عليك قبل الحفظ.</p>
        </div>
        <div class="x-actions">
            <a href="{{ route('products.import.template') }}" class="x-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m-5-5 5 5 5-5M4 21h16"/></svg>
                تنزيل القالب
            </a>
            <a href="{{ route('products.index') }}" class="x-btn is-ghost">المنتجات</a>
        </div>
    </div>

    @include('partials.x-flash')

    @unless ($result)
        <div class="x-stats" style="grid-template-columns:repeat(auto-fit,minmax(240px,1fr))">
            <div class="x-stat"><span>١. نزّل القالب</span><em style="font-size:13.5px;color:var(--ink);margin-top:6px;line-height:1.6">ملف Excel جاهز بالأعمدة الصحيحة و3 أمثلة. امسح الأمثلة واكتب أصنافك.</em></div>
            <div class="x-stat"><span>٢. عبّئ الأصناف</span><em style="font-size:13.5px;color:var(--ink);margin-top:6px;line-height:1.6">الأسعار <b>للحبة/الوحدة الأساسية</b>. العلبة تُكتب في «الوحدة الكبيرة» مع عدد حباتها.</em></div>
            <div class="x-stat"><span>٣. ارفع وراجع</span><em style="font-size:13.5px;color:var(--ink);margin-top:6px;line-height:1.6">تظهر لك معاينة لكل صف وأخطاؤه. لا يُحفظ شيء قبل ضغط «استيراد».</em></div>
        </div>
    @endunless

    <section class="erp-card">
        <div class="erp-card-head"><h2>{{ $result ? 'رفع ملف آخر' : 'رفع الملف' }}</h2></div>
        <form method="POST" action="{{ route('products.import.preview') }}" enctype="multipart/form-data" class="x-body x-fields" id="upForm">
            @csrf
            <div class="x-field" style="grid-column:span 2">
                <label for="file">ملف Excel (.xlsx) أو CSV</label>
                <input type="file" name="file" id="file" accept=".xlsx,.csv" required style="padding:8px 10px;min-height:44px">
                <small>حتى 5 ميغابايت و{{ \App\Services\ProductImportService::MAX_ROWS }} صنف في الملف الواحد.</small>
            </div>
            @if (!\App\Support\Tenancy::branchId() && $branches->count() > 1)
                <div class="x-field">
                    <label for="branch">الكميات تدخل مخزون فرع</label>
                    <select name="branch_id" id="branch">
                        @foreach ($branches as $b)<option value="{{ $b->id }}" @selected(($preview['branch_id'] ?? null) == $b->id)>{{ $b->name }}</option>@endforeach
                    </select>
                </div>
            @endif
            <div class="x-actions"><button class="x-btn" id="upBtn">فحص الملف</button></div>
        </form>
    </section>

    @if ($result)
        <div class="x-stats">
            <div class="x-stat"><span>صفوف في الملف</span><strong class="num">{{ $s['total'] }}</strong><em>{{ $preview['file'] }}</em></div>
            <div class="x-stat is-good"><span>أصناف جديدة</span><strong class="num">{{ $s['create'] }}</strong></div>
            <div class="x-stat"><span>أصناف موجودة</span><strong class="num">{{ $s['update'] }}</strong><em>تُطابق بالباركود أو الاسم</em></div>
            <div class="x-stat {{ $s['error'] ? 'is-bad' : '' }}"><span>صفوف فيها أخطاء</span><strong class="num">{{ $s['error'] }}</strong><em>{{ $s['error'] ? 'لن تُستورد' : 'ممتاز' }}</em></div>
            <div class="x-stat is-main"><span>كمية تدخل المخزون</span><strong class="num">{{ number_format($s['qty']) }}</strong><em>فرع {{ $preview['branch'] }}</em></div>
        </div>

        @if ($result['unknown_headers'])
            <div class="x-note is-warn">أعمدة لم يتعرف عليها النظام وستُتجاهل: {{ implode('، ', $result['unknown_headers']) }}</div>
        @endif

        <section class="erp-card">
            <div class="erp-card-head">
                <h2>معاينة الصفوف</h2>
                <nav class="x-tabs" id="rowTabs">
                    <a href="#" data-f="all" class="is-active">الكل ({{ $s['total'] }})</a>
                    <a href="#" data-f="error">الأخطاء ({{ $s['error'] }})</a>
                    <a href="#" data-f="create">الجديدة ({{ $s['create'] }})</a>
                    <a href="#" data-f="update">الموجودة ({{ $s['update'] }})</a>
                </nav>
            </div>
            <div class="x-table-wrap" style="max-height:620px;overflow-y:auto">
                <table class="x-table">
                    <thead style="position:sticky;top:0;z-index:1">
                        <tr><th>صف</th><th>الصنف</th><th>الباركود</th><th class="n">الشراء</th><th class="n">البيع</th><th class="n">الكمية</th><th>الدفعة / الانتهاء</th><th>الوحدة الكبيرة</th><th>النتيجة</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($result['rows'] as $r)
                            @php $d = $r['data']; @endphp
                            <tr data-a="{{ $r['action'] }}" style="{{ $r['action'] === 'error' ? 'background:#FFF7F6' : '' }}">
                                <td class="num muted">{{ $r['line'] }}</td>
                                <td>
                                    <strong>{{ $d['name'] ?: '—' }}</strong>
                                    @if ($d['category'] || $d['unit'])<div class="muted">{{ collect([$d['category'], $d['unit']])->filter()->implode('، ') }}</div>@endif
                                    @if ($d['controlled'])<span class="x-badge is-warn">رقابة</span>@endif
                                </td>
                                <td class="num muted">{{ $d['barcode'] ?? '—' }}</td>
                                <td class="n">{{ $q($d['purchase']) }}</td>
                                <td class="n">{{ $q($d['price']) }}</td>
                                <td class="n">{{ $d['qty'] ?: '—' }}</td>
                                <td class="muted">
                                    @if ($d['batch'])<div>{{ $d['batch'] }}</div>@endif
                                    @if ($d['expiry'])<div class="num" style="white-space:nowrap">{{ $d['expiry'] }}</div>@endif
                                    @if (!$d['batch'] && !$d['expiry'])—@endif
                                </td>
                                <td class="muted">{{ $d['big_unit'] ? $d['big_unit'] . ' × ' . $d['big_factor'] . ($d['big_price'] ? ' = ' . $q($d['big_price']) : '') : '—' }}</td>
                                <td style="min-width:220px">
                                    @if ($r['action'] === 'error')
                                        <span class="x-badge is-bad">لن يُستورد</span>
                                        <ul style="margin:6px 0 0;padding-inline-start:16px;color:var(--red);font-size:12.5px">@foreach ($r['errors'] as $e)<li>{{ $e }}</li>@endforeach</ul>
                                    @else
                                        <span class="x-badge {{ $r['action'] === 'create' ? 'is-good' : 'is-info' }}">{{ $r['action'] === 'create' ? 'صنف جديد' : 'موجود: يُضاف للمخزون' }}</span>
                                    @endif
                                    @foreach ($r['warnings'] as $w)<div style="color:var(--amber);font-size:12.5px;margin-top:4px">⚠ {{ $w }}</div>@endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <form method="POST" action="{{ route('products.import.store') }}" class="x-body is-top x-actions" style="justify-content:space-between;border-top:1px solid var(--line-soft)" id="impForm">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <label class="x-check">
                    <input type="checkbox" name="update_prices" value="1" checked>
                    تحديث أسعار الأصناف الموجودة من الملف
                </label>
                <div class="x-actions">
                    @if ($s['error'])<span style="font-size:13px;color:var(--muted)">صحّح الأخطاء في الملف وارفعه من جديد، أو استورد الصفوف السليمة الآن.</span>@endif
                    <button class="x-btn" @disabled(!$valid)>استيراد {{ $valid }} صنف</button>
                </div>
            </form>
        </section>
    @endif
</div>

<script>
(function () {
    const up = document.getElementById('upForm');
    up?.addEventListener('submit', () => { const b = document.getElementById('upBtn'); b.disabled = true; b.textContent = 'جاري الفحص...'; });

    const imp = document.getElementById('impForm');
    imp?.addEventListener('submit', e => {
        if (!confirm('تأكيد الاستيراد؟ ستُضاف الأصناف والكميات للمخزون.')) { e.preventDefault(); return; }
        const b = imp.querySelector('button'); b.disabled = true; b.textContent = 'جاري الاستيراد...';
    });

    document.querySelectorAll('#rowTabs a').forEach(a => a.addEventListener('click', e => {
        e.preventDefault();
        document.querySelectorAll('#rowTabs a').forEach(x => x.classList.toggle('is-active', x === a));
        document.querySelectorAll('tbody tr[data-a]').forEach(tr => tr.hidden = a.dataset.f !== 'all' && tr.dataset.a !== a.dataset.f);
    }));
})();
</script>
@endsection
