@extends('layouts.app')

@section('title', 'ملصقات الباركود')

@section('content')
<div class="x-page" style="max-width:1100px">
    <div class="x-head">
        <div>
            <h2>ملصقات الباركود</h2>
            <p>اختر الأصناف وعدد النسخ، ثم اطبع على طابعة الملصقات أو على ورق A4. الصنف الذي ليس له باركود يُنشأ له رمز من رقمه (مثل <span class="num">P000042</span>) ويُحفظ عليه، فيقرؤه قارئ نقطة البيع.</p>
        </div>
    </div>

    @include('partials.x-flash')

    <form method="POST" action="{{ route('barcodes.print') }}" target="_blank" class="x-split" id="bcForm">
        @csrf
        <section class="erp-card">
            <div class="erp-card-head">
                <label style="flex:1;max-width:380px">
                    <span style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)">بحث</span>
                    <input type="search" id="bcSearch" placeholder="ابحث باسم الصنف أو الباركود" style="width:100%;padding:0 12px">
                </label>
            </div>
            <div class="x-table-wrap" style="max-height:560px;overflow-y:auto">
                <table class="x-table">
                    <thead><tr><th>الصنف</th><th>الباركود</th><th class="n">السعر</th><th style="width:1%"></th></tr></thead>
                    <tbody>
                        @foreach ($products as $p)
                            <tr data-name="{{ mb_strtolower($p->name . ' ' . $p->barcode . ' ' . $p->sku) }}">
                                <td>{{ $p->name }}</td>
                                <td class="num muted">{!! $p->barcode ? e($p->barcode) : '<span class="x-badge">سيُنشأ تلقائياً</span>' !!}</td>
                                <td class="n">{{ number_format($p->selling_price, 2) }}</td>
                                <td><button type="button" class="x-btn is-light is-sm bc-add" data-id="{{ $p->id }}" data-name="{{ $p->name }}">إضافة</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="erp-card x-body is-top" style="display:grid;gap:12px;position:sticky;top:12px">
            <strong style="font-size:15px">للطباعة <span class="x-badge is-info num" id="bcCount">0</span></strong>
            <div id="bcList" style="display:grid;gap:8px"></div>
            <div class="x-empty" id="bcEmpty" style="padding:12px">اضغط «إضافة» بجانب الصنف.</div>
            <div class="x-field">
                <label for="bcSize">مقاس الملصق</label>
                <select name="size" id="bcSize">
                    <option value="38x25">38 × 25 مم (صغير)</option>
                    <option value="50x30">50 × 30 مم</option>
                    <option value="58x40">58 × 40 مم (كبير)</option>
                </select>
            </div>
            <label class="x-check"><input type="hidden" name="show_price" value="0"><input type="checkbox" name="show_price" value="1" checked> إظهار السعر</label>
            <button class="x-btn" id="bcPrint" disabled>طباعة الملصقات</button>
        </aside>
    </form>
</div>

<script>
(function () {
    const list = document.getElementById('bcList'), empty = document.getElementById('bcEmpty');
    const norm = s => String(s || '').toLowerCase().replace(/[إأآ]/g, 'ا').replace(/ى/g, 'ي').replace(/ة/g, 'ه');
    let i = 0;

    function refresh() {
        const rows = list.querySelectorAll('.bc-row');
        let total = 0;
        rows.forEach(r => total += parseInt(r.querySelector('input[type=number]').value || 0, 10));
        document.getElementById('bcCount').textContent = total;
        empty.hidden = rows.length > 0;
        document.getElementById('bcPrint').disabled = !rows.length;
    }

    document.querySelectorAll('.bc-add').forEach(b => b.addEventListener('click', () => {
        const existing = list.querySelector(`[data-id="${b.dataset.id}"]`);
        if (existing) { const q = existing.querySelector('input[type=number]'); q.value = parseInt(q.value, 10) + 1; refresh(); return; }
        const k = i++;
        const row = document.createElement('div');
        row.className = 'bc-row'; row.dataset.id = b.dataset.id;
        row.style.cssText = 'display:flex;align-items:center;gap:8px';
        row.innerHTML = `<input type="hidden" name="items[${k}][product_id]" value="${b.dataset.id}">
            <span style="flex:1;font-size:13.5px;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"></span>
            <input type="number" name="items[${k}][copies]" value="1" min="1" max="500" class="x-qty" style="width:70px !important" aria-label="عدد النسخ">
            <button type="button" class="x-btn is-danger is-sm" aria-label="إزالة">×</button>`;
        row.querySelector('span').textContent = b.dataset.name;
        row.querySelector('input[type=number]').addEventListener('input', refresh);
        row.querySelector('button').addEventListener('click', () => { row.remove(); refresh(); });
        list.appendChild(row);
        refresh();
    }));

    document.getElementById('bcSearch').addEventListener('input', e => {
        const q = norm(e.target.value);
        document.querySelectorAll('tbody tr[data-name]').forEach(r => r.hidden = q && !norm(r.dataset.name).includes(q));
    });
    refresh();
})();
</script>
@endsection
