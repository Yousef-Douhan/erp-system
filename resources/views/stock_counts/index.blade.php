@extends('layouts.app')

@section('title', 'الجرد')

@section('content')
<div class="x-page">
    <div class="x-head">
        <div>
            <h2>الجرد</h2>
            <p>أنشئ ورقة جرد لكل الأصناف أو لتصنيف واحد، وأدخل الكمية الموجودة على الرف. عند الترحيل يضبط النظام الرصيد ويسجل الفروقات كحركات «تسوية جرد».</p>
        </div>
    </div>

    @include('partials.x-flash')

    <section class="erp-card">
        <div class="erp-card-head"><h2>جرد جديد</h2></div>
        <form method="POST" action="{{ route('stock-counts.store') }}" class="x-body x-fields">
            @csrf
            @if (!\App\Support\Tenancy::branchId() && $branches->count() > 1)
                <div class="x-field">
                    <label for="cbranch">الفرع</label>
                    <select name="branch_id" id="cbranch" required>
                        @foreach ($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                    </select>
                </div>
            @endif
            <div class="x-field">
                <label for="cat">النطاق</label>
                <select name="category_id" id="cat">
                    <option value="">كل الأصناف النشطة</option>
                    @foreach ($categories as $c)
                        <option value="{{ $c->id }}">تصنيف: {{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="x-field">
                <label for="notes">ملاحظة (اختياري)</label>
                <input type="text" name="notes" id="notes" maxlength="500" placeholder="مثال: جرد نهاية الشهر">
            </div>
            <div class="x-actions"><button class="x-btn">إنشاء ورقة الجرد</button></div>
        </form>
    </section>

    <section class="erp-card">
        <div class="erp-card-head"><h2>أوراق الجرد</h2></div>
        @if ($counts->isEmpty())
            <div class="x-empty">لم يُنشأ أي جرد بعد.</div>
        @else
            <div class="x-table-wrap">
                <table class="x-table">
                    <thead><tr><th>الرقم</th><th>النطاق</th><th>أنشأه</th><th>التاريخ</th><th class="n">الأصناف</th><th class="n">تم عدّها</th><th class="n">بفروقات</th><th>الحالة</th></tr></thead>
                    <tbody>
                        @foreach ($counts as $c)
                            <tr>
                                <td><a href="{{ route('stock-counts.show', $c) }}" class="num">{{ $c->count_number }}</a></td>
                                <td>{{ $c->scope }}</td>
                                <td class="muted">{{ $c->user->username ?? '—' }}</td>
                                <td class="muted">{{ $c->created_at->format('Y-m-d') }}</td>
                                <td class="n">{{ $c->items_count }}</td>
                                <td class="n">{{ $c->counted_count }}</td>
                                <td class="n">{{ $c->isDraft() ? '—' : $c->diff_count }}</td>
                                <td>
                                    @if ($c->isDraft())
                                        <span class="x-badge is-warn">مسودة</span>
                                    @else
                                        <span class="x-badge is-good">مُرحَّل {{ $c->posted_at?->format('Y-m-d') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="x-pages">{{ $counts->links() }}</div>
        @endif
    </section>
</div>
@endsection
