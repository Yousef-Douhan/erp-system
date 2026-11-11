@extends('layouts.app')

@section('title', 'نقل المخزون')

@section('content')
<div class="x-page">
    <div class="x-head">
        <div>
            <h2>نقل المخزون بين المستودعات</h2>
            <p>انقل كميات من دفعات مستودع إلى مستودع آخر. رصيد الصنف الكلي لا يتغير، ويُسجَّل التحويل في الحركات المخزنية.</p>
        </div>
        <div class="x-actions"><a href="{{ route('warehouses.index') }}" class="x-btn is-ghost">المستودعات</a></div>
    </div>

    @include('partials.x-flash')

    @if ($targets->count() < 2)
        <div class="x-note is-warn">تحتاج مستودعين على الأقل للنقل. <a href="{{ route('warehouses.index') }}" style="color:inherit;font-weight:700">أضف مستودعاً</a>.</div>
    @else
        <form method="POST" action="{{ route('stock-transfers.store') }}" class="erp-card" id="trfForm">
            @csrf
            <div class="erp-card-head"><h2>تحويل جديد</h2></div>
            <div class="x-body x-fields">
                <div class="x-field">
                    <label for="from">من مستودع</label>
                    <select name="from_warehouse_id" id="from" required>
                        @foreach ($warehouses as $w)<option value="{{ $w->id }}" @selected(old('from_warehouse_id') == $w->id)>{{ $w->name }}{{ $w->branch ? ' — ' . $w->branch->name : '' }}</option>@endforeach
                    </select>
                </div>
                <div class="x-field">
                    <label for="to">إلى مستودع</label>
                    <select name="to_warehouse_id" id="to" required>
                        @foreach ($targets as $w)<option value="{{ $w->id }}" @selected(old('to_warehouse_id', $targets->firstWhere('id', '!=', $warehouses->first()?->id)?->id) == $w->id)>{{ $w->name }}{{ $w->branch ? ' — ' . $w->branch->name : '' }}</option>@endforeach
                    </select>
                </div>
                <div class="x-field"><label for="tnotes">ملاحظة</label><input type="text" name="notes" id="tnotes" maxlength="500"></div>
            </div>

            <div class="x-table-wrap">
                <table class="x-table">
                    <thead><tr><th>الدفعة</th><th class="n">المتوفر</th><th style="text-align:center">الكمية المنقولة</th><th style="width:1%"></th></tr></thead>
                    <tbody id="trfLines"></tbody>
                </table>
            </div>
            <div class="x-body is-top x-actions" style="justify-content:space-between">
                <button type="button" class="x-btn is-ghost is-sm" id="trfAdd">+ صنف آخر</button>
                <button class="x-btn">تنفيذ التحويل</button>
            </div>
        </form>
    @endif

    <section class="erp-card">
        <div class="erp-card-head"><h2>التحويلات السابقة</h2></div>
        @if ($transfers->isEmpty())
            <div class="x-empty">لا توجد تحويلات بعد.</div>
        @else
            <div class="x-table-wrap">
                <table class="x-table">
                    <thead><tr><th>الرقم</th><th>من</th><th>إلى</th><th>الأصناف</th><th>بواسطة</th><th>التاريخ</th></tr></thead>
                    <tbody>
                        @foreach ($transfers as $t)
                            <tr>
                                <td class="num">{{ $t->transfer_number }}</td>
                                <td>{{ $t->fromWarehouse->name ?? '—' }}</td>
                                <td>{{ $t->toWarehouse->name ?? '—' }}</td>
                                <td>
                                    @foreach ($t->items as $it)
                                        <div>{{ $it->product->name ?? '—' }} <span class="muted">× <span class="num">{{ $it->quantity }}</span>{{ $it->fromBatch ? ' (دفعة ' . $it->fromBatch->batch_number . ')' : '' }}</span></div>
                                    @endforeach
                                </td>
                                <td class="muted">{{ $t->user->username ?? '—' }}</td>
                                <td class="muted">{{ $t->created_at->format('Y-m-d H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="x-pages">{{ $transfers->links() }}</div>
        @endif
    </section>
</div>

@if ($targets->count() >= 2)
<script>
(function () {
    const batches = @json($batches);
    const from = document.getElementById('from'), body = document.getElementById('trfLines');
    let n = 0;
    const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

    function options() {
        const list = batches.filter(b => String(b.warehouse) === from.value);
        return list.length
            ? '<option value="">اختر الدفعة...</option>' + list.map(b => `<option value="${b.id}" data-qty="${b.qty}">${esc(b.label)}</option>`).join('')
            : '<option value="">لا توجد دفعات بكمية في هذا المستودع</option>';
    }

    function addLine() {
        const i = n++;
        const tr = document.createElement('tr');
        tr.innerHTML = `<td><select name="items[${i}][batch_id]" required style="width:100%;padding:0 10px">${options()}</select></td>
            <td class="n avail">—</td>
            <td style="text-align:center"><input type="number" name="items[${i}][quantity]" min="1" required class="x-qty" aria-label="الكمية"></td>
            <td><button type="button" class="x-btn is-danger is-sm" aria-label="حذف السطر">×</button></td>`;
        const sel = tr.querySelector('select'), qty = tr.querySelector('input');
        sel.addEventListener('change', () => {
            const q = sel.selectedOptions[0]?.dataset.qty;
            tr.querySelector('.avail').textContent = q ?? '—';
            qty.max = q || '';
        });
        tr.querySelector('button').addEventListener('click', () => { if (body.children.length > 1) tr.remove(); });
        body.appendChild(tr);
    }

    from.addEventListener('change', () => { body.innerHTML = ''; addLine(); });
    document.getElementById('trfAdd').addEventListener('click', addLine);
    addLine();
})();
</script>
@endif
@endsection
