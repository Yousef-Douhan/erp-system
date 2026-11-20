@extends('layouts.app')

@section('title', 'الدفعات وتواريخ الصلاحية')

@section('content')
@php
    $tabs = ['active' => 'المتوفرة', 'expiring' => 'تنتهي خلال 90 يوماً', 'expired' => 'منتهية الصلاحية', 'all' => 'الكل (مع الفارغة)'];
    $canDispose = Route::has('disposals.store') && auth()->user()->can('stock_count');
@endphp

<style>
    .cr-dialog { position: fixed; inset: 0; margin: auto; height: fit-content; max-height: calc(100vh - 32px); overflow: auto; border: 0; border-radius: var(--radius); padding: 0; width: min(440px, calc(100vw - 32px)); box-shadow: 0 20px 50px rgba(16, 42, 43, .25); }
    .cr-dialog::backdrop { background: rgba(16, 42, 43, .45); }
    .cr-dialog form { display: grid; gap: 14px; padding: 20px; }
    .cr-dialog h3 { margin: 0; font-size: 17px; }
    .cr-dialog .cr-sub { margin: -8px 0 0; color: var(--muted); font-size: 13.5px; }
    .cr-dialog .x-actions { justify-content: flex-start; }
</style>

<div class="x-page">
    <div class="x-head">
        <div>
            <h2>الدفعات وتواريخ الصلاحية</h2>
            <p>للمتابعة فقط: الكميات تدخل بفاتورة شراء وتخرج ببيع أو نقل أو مرتجع للمورد أو إتلاف، وكل حركة تُسجَّل في «حركات المخزون».</p>
        </div>
        <div class="x-actions">
            @if (Route::has('disposals.index') && auth()->user()->can('stock_count'))
                <a href="{{ route('disposals.index') }}" class="x-btn is-ghost">سجل الإتلاف</a>
            @endif
            @can('create_purchases')
                <a href="{{ route('purchases.index') }}" class="x-btn">إدخال بضاعة (فاتورة شراء)</a>
            @endcan
        </div>
    </div>

    @include('partials.x-flash')

    <div class="x-stats">
        <a href="{{ route('batches.index', ['status' => 'expired']) }}" class="x-stat is-bad" style="text-decoration:none">
            <span>دفعات منتهية فيها كمية</span><strong class="num">{{ $counts['expired'] }}</strong>
            <em>قيمتها {{ number_format($counts['expired_value'], 2) }} ₪ — لا تُباع، أتلفها أو أرجعها للمورد</em>
        </a>
        <a href="{{ route('batches.index', ['status' => 'expiring']) }}" class="x-stat is-warn" style="text-decoration:none">
            <span>تنتهي خلال 90 يوماً</span><strong class="num">{{ $counts['expiring'] }}</strong><em>بِعها أولاً أو أرجعها للمورد قبل الانتهاء</em>
        </a>
    </div>

    <section class="erp-card">
        <div class="x-body is-top no-print" style="display:grid;gap:12px">
            <nav class="x-tabs" aria-label="حالة الدفعات">
                @foreach ($tabs as $key => $label)
                    <a href="{{ request()->fullUrlWithQuery(['status' => $key, 'page' => null]) }}" class="{{ $status === $key ? 'is-active' : '' }}">{{ $label }}</a>
                @endforeach
            </nav>
            <form method="GET" class="x-fields">
                <input type="hidden" name="status" value="{{ $status }}">
                <div class="x-field"><label for="q">بحث</label><input type="search" id="q" name="q" value="{{ request('q') }}" placeholder="اسم الصنف أو رقم الدفعة"></div>
                @if ($warehouses->count() > 1)
                    <div class="x-field">
                        <label for="wh">المستودع</label>
                        <select id="wh" name="warehouse_id">
                            <option value="">كل المستودعات</option>
                            @foreach ($warehouses as $w)
                                <option value="{{ $w->id }}" @selected((int) request('warehouse_id') === $w->id)>{{ $w->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="x-actions"><button class="x-btn">عرض</button>@if (request()->hasAny(['q', 'warehouse_id']))<a href="{{ route('batches.index', ['status' => $status]) }}" class="x-btn is-ghost">مسح</a>@endif</div>
            </form>
        </div>

        @if ($batches->isEmpty())
            <div class="x-empty">لا توجد دفعات في هذا التصنيف.</div>
        @else
            <div class="x-table-wrap">
                <table class="x-table">
                    <thead>
                        <tr><th>الصنف</th><th>رقم الدفعة</th><th>المستودع</th><th class="n">الكمية</th><th class="n">التكلفة</th><th>الانتهاء</th><th class="no-print"></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($batches as $batch)
                            @php
                                $exp = $batch->expiry_date ? \Illuminate\Support\Carbon::parse($batch->expiry_date) : null;
                                $days = $exp ? (int) today()->diffInDays($exp, false) : null;
                                $cost = (float) $batch->cost_price > 0 ? (float) $batch->cost_price : (float) ($batch->product->purchase_price ?? 0);
                            @endphp
                            <tr>
                                <td><strong>{{ $batch->product->name ?? '—' }}</strong></td>
                                <td class="num">{{ $batch->batch_number }}</td>
                                <td class="muted">{{ $batch->warehouse->name ?? '—' }}</td>
                                <td class="n" style="font-weight:700">{{ $batch->quantity }}</td>
                                <td class="n">{{ number_format($cost, 2) }}</td>
                                <td>
                                    @if (!$exp)
                                        <span class="muted">—</span>
                                    @else
                                        <span class="num">{{ $exp->toDateString() }}</span>
                                        @if ($days < 0)
                                            <span class="x-badge is-bad">منتهية منذ {{ abs($days) }} يوم</span>
                                        @elseif ($days <= 90)
                                            <span class="x-badge is-warn">باقي {{ $days }} يوم</span>
                                        @endif
                                    @endif
                                </td>
                                <td class="no-print" style="white-space:nowrap">
                                    <button type="button" class="x-btn is-ghost is-sm js-dates"
                                            data-url="{{ route('batches.update', $batch) }}" data-label="{{ ($batch->product->name ?? '') . ' — ' . $batch->batch_number }}"
                                            data-mfg="{{ $batch->manufacturing_date }}" data-exp="{{ $exp?->toDateString() }}">التواريخ</button>
                                    @if ($canDispose && $batch->quantity > 0)
                                        <button type="button" class="x-btn is-danger is-sm js-dispose"
                                                data-url="{{ route('disposals.store', $batch) }}" data-label="{{ ($batch->product->name ?? '') . ' — ' . $batch->batch_number }}"
                                                data-max="{{ $batch->quantity }}" data-expired="{{ $days !== null && $days < 0 ? 1 : 0 }}">إتلاف</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="x-pages">{{ $batches->links() }}</div>
        @endif
    </section>
</div>

<dialog class="cr-dialog" id="datesDialog">
    <form method="POST" id="datesForm">
        @csrf
        @method('PUT')
        <h3>تصحيح تواريخ الدفعة</h3>
        <p class="cr-sub" id="datesLabel"></p>
        <div class="x-field"><label for="dMfg">تاريخ الإنتاج</label><input type="date" name="manufacturing_date" id="dMfg"></div>
        <div class="x-field"><label for="dExp">تاريخ الانتهاء</label><input type="date" name="expiry_date" id="dExp"></div>
        <div class="x-note">الكمية ورقم الدفعة لا يتغيران من هنا.</div>
        <div class="x-actions"><button class="x-btn">حفظ</button><button type="button" class="x-btn is-ghost" data-close>إلغاء</button></div>
    </form>
</dialog>

@if ($canDispose)
<dialog class="cr-dialog" id="disposeDialog">
    <form method="POST" id="disposeForm">
        @csrf
        <h3>إتلاف من المخزون</h3>
        <p class="cr-sub" id="disposeLabel"></p>
        <div class="x-field"><label for="dQty">الكمية</label><input type="number" name="quantity" id="dQty" min="1" step="1" required></div>
        <div class="x-field">
            <label for="dReason">السبب</label>
            <select name="reason" id="dReason">
                @foreach ($reasons as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="x-field"><label for="dNotes">ملاحظات (اختياري)</label><input type="text" name="notes" id="dNotes" maxlength="255" placeholder="مثال: كرتونة مبلولة"></div>
        <div class="x-note">الكمية تخرج من المخزون نهائياً وتُسجَّل كخسارة بسعر التكلفة في تقرير الأرباح. إن كان المورد يقبل إرجاعها فاعمل «مرتجع للمورد» من فاتورة الشراء بدلاً من ذلك.</div>
        <div class="x-actions"><button class="x-btn is-danger">تأكيد الإتلاف</button><button type="button" class="x-btn is-ghost" data-close>إلغاء</button></div>
    </form>
</dialog>
@endif

<script>
(function () {
    document.querySelectorAll('.cr-dialog [data-close]').forEach(b => b.addEventListener('click', () => b.closest('dialog').close()));

    const dd = document.getElementById('datesDialog');
    document.querySelectorAll('.js-dates').forEach(b => b.addEventListener('click', () => {
        document.getElementById('datesForm').action = b.dataset.url;
        document.getElementById('datesLabel').textContent = b.dataset.label;
        document.getElementById('dMfg').value = b.dataset.mfg || '';
        document.getElementById('dExp').value = b.dataset.exp || '';
        dd.showModal();
    }));

    const xd = document.getElementById('disposeDialog');
    if (!xd) return;
    document.querySelectorAll('.js-dispose').forEach(b => b.addEventListener('click', () => {
        document.getElementById('disposeForm').action = b.dataset.url;
        document.getElementById('disposeLabel').textContent = b.dataset.label + ' — المتوفر ' + b.dataset.max;
        const q = document.getElementById('dQty');
        q.max = b.dataset.max; q.value = b.dataset.max;
        document.getElementById('dReason').value = b.dataset.expired === '1' ? 'expired' : 'damaged';
        xd.showModal();
    }));
})();
</script>
@endsection
