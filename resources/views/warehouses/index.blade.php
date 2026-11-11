@extends('layouts.app')

@section('title', 'المستودعات')

@section('content')
<div class="x-page" style="max-width:1100px">
    <div class="x-head">
        <div>
            <h2>المستودعات</h2>
            <p>كل دفعة دواء موجودة في مستودع. اربط كل مستودع بفرعه، ثم استخدم «نقل المخزون» لتحويل الكميات بين الفروع.</p>
        </div>
        <div class="x-actions"><a href="{{ route('stock-transfers.index') }}" class="x-btn is-ghost">نقل المخزون</a></div>
    </div>

    @include('partials.x-flash')

    @if ($unassigned > 0)
        <div class="x-note">يوجد <b class="num">{{ $unassigned }}</b> وحدة في دفعات بلا مستودع محدد، وتُعامل كأنها في المستودع الرئيسي.</div>
    @endif

    <section class="erp-card">
        <div class="erp-card-head"><h2>مستودع جديد</h2></div>
        <form method="POST" action="{{ route('warehouses.store') }}" class="x-body x-fields">
            @csrf
            <div class="x-field"><label for="wname">الاسم</label><input type="text" id="wname" name="name" required maxlength="255" placeholder="مثال: مستودع فرع الرمال"></div>
            <div class="x-field">
                <label for="wbranch">الفرع</label>
                <select id="wbranch" name="branch_id">
                    <option value="">بدون فرع</option>
                    @foreach ($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                </select>
            </div>
            <div class="x-actions"><button class="x-btn">إضافة</button></div>
        </form>
    </section>

    <section class="erp-card">
        @if ($warehouses->isEmpty())
            <div class="x-empty">لا توجد مستودعات بعد.</div>
        @else
            <div class="x-table-wrap">
                <table class="x-table">
                    <thead><tr><th>المستودع</th><th>الفرع</th><th class="n">دفعات فيها كمية</th><th class="n">إجمالي الكمية</th><th style="width:1%"></th></tr></thead>
                    <tbody>
                        @foreach ($warehouses as $w)
                            <tr>
                                <td colspan="2" style="padding:6px 14px">
                                    <form method="POST" action="{{ route('warehouses.update', $w) }}" class="x-actions" style="flex-wrap:nowrap">
                                        @csrf
                                        @method('PUT')
                                        <input type="text" name="name" value="{{ $w->name }}" required aria-label="اسم المستودع" style="padding:0 10px;min-width:160px">
                                        <select name="branch_id" aria-label="الفرع" style="padding:0 10px;min-width:140px">
                                            <option value="">بدون فرع</option>
                                            @foreach ($branches as $b)<option value="{{ $b->id }}" @selected((int) $w->branch_id === (int) $b->id)>{{ $b->name }}</option>@endforeach
                                        </select>
                                        <button class="x-btn is-light is-sm">حفظ</button>
                                    </form>
                                </td>
                                <td class="n">{{ $w->batches_count }}</td>
                                <td class="n">{{ (int) $w->stock_qty }}</td>
                                <td>
                                    <form method="POST" action="{{ route('warehouses.destroy', $w) }}" onsubmit="return confirm('حذف المستودع {{ $w->name }}؟')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="x-btn is-danger is-sm" @disabled($w->batches_count > 0) title="{{ $w->batches_count ? 'فيه كميات' : 'حذف' }}">حذف</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
@endsection
