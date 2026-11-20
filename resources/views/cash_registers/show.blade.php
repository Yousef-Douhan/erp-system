@extends('layouts.app')

@section('title', 'كشف ' . $register->name)

@section('content')
@php
    $m = fn ($n) => number_format((float) $n, 2);
    $off = $register->status !== 'active';
@endphp

<div class="x-page">
    <div class="x-head">
        <div>
            <h2>{{ $register->name }}</h2>
            <p>{{ $register->branch->name ?? '—' }} · كل حركة دخلت أو خرجت من هذه الخزينة، ومن سجّلها، والرصيد بعدها.</p>
        </div>
        <div class="x-actions no-print">
            <a href="{{ route('cash-registers.index') }}" class="x-btn is-ghost">كل الخزائن</a>
            @unless ($off)
                <button type="button" class="x-btn is-light" data-cash="deposit" data-id="{{ $register->id }}" data-name="{{ $register->name }}" data-balance="{{ (float) $register->current_balance }}" data-url="{{ route('cash-registers.deposit', $register) }}">إيداع</button>
                <button type="button" class="x-btn is-light" data-cash="withdraw" data-id="{{ $register->id }}" data-name="{{ $register->name }}" data-balance="{{ (float) $register->current_balance }}" data-url="{{ route('cash-registers.withdraw', $register) }}">سحب</button>
                <button type="button" class="x-btn" data-cash="transfer" data-id="{{ $register->id }}" data-name="{{ $register->name }}" data-balance="{{ (float) $register->current_balance }}">تحويل</button>
            @endunless
            <button type="button" class="x-btn is-ghost" onclick="window.print()">طباعة</button>
        </div>
    </div>

    @include('partials.x-flash')

    <div class="x-stats">
        <div class="x-stat"><span>الرصيد في {{ $from->format('Y-m-d') }}</span><strong>{{ $m($openingBalance) }} <small>₪</small></strong></div>
        <div class="x-stat is-good"><span>الداخل في الفترة</span><strong>{{ $m($cashIn) }} <small>₪</small></strong></div>
        <div class="x-stat is-bad"><span>الخارج في الفترة</span><strong>{{ $m($cashOut) }} <small>₪</small></strong></div>
        <div class="x-stat is-main"><span>الرصيد الآن</span><strong>{{ $m($register->current_balance) }} <small>₪</small></strong>
            <em>{{ $register->openShift ? 'وردية مفتوحة: ' . ($register->openShift->user->username ?? '') : 'لا توجد وردية مفتوحة' }}</em></div>
    </div>

    <section class="erp-card">
        <div class="x-body is-top no-print">
            <form method="GET" class="x-fields">
                <div class="x-field"><label for="from">من</label><input type="date" id="from" name="from" value="{{ $from->format('Y-m-d') }}"></div>
                <div class="x-field"><label for="to">إلى</label><input type="date" id="to" name="to" value="{{ $to->format('Y-m-d') }}"></div>
                <div class="x-field">
                    <label for="type">نوع الحركة</label>
                    <select id="type" name="type">
                        <option value="">كل الأنواع</option>
                        @foreach ($types as $key => $label)
                            <option value="{{ $key }}" @selected($type === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="x-actions"><button class="x-btn">عرض</button></div>
            </form>
        </div>

        @if ($movements->isEmpty())
            <div class="x-empty">لا توجد حركات في هذه الفترة.</div>
        @else
            <div class="x-table-wrap">
                <table class="x-table">
                    <thead>
                        <tr><th>التاريخ</th><th>النوع</th><th>البيان</th><th>بواسطة</th><th class="n">داخل</th><th class="n">خارج</th><th class="n">الرصيد بعدها</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($movements as $mv)
                            <tr>
                                <td class="muted num">{{ $mv->created_at->format('Y-m-d H:i') }}</td>
                                <td><span class="x-badge {{ $mv->isIn() ? 'is-good' : 'is-bad' }}">{{ $mv->typeLabel() }}</span></td>
                                <td>
                                    @if ($url = $mv->referenceUrl())
                                        <a href="{{ $url }}">{{ $mv->description ?: '—' }}</a>
                                    @else
                                        {{ $mv->description ?: '—' }}
                                    @endif
                                </td>
                                <td class="muted">{{ $mv->user->username ?? 'النظام' }}</td>
                                <td class="n">{{ $mv->isIn() ? $m($mv->amount) : '' }}</td>
                                <td class="n">{{ $mv->isIn() ? '' : $m($mv->amount) }}</td>
                                <td class="n"><strong>{{ $m($mv->balance_after) }}</strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="x-pages no-print">{{ $movements->links() }}</div>
        @endif
    </section>

    @if ($byType->isNotEmpty())
        <section class="erp-card">
            <div class="erp-card-head"><h2>ملخص الفترة حسب النوع</h2></div>
            <div class="x-table-wrap">
                <table class="x-table">
                    <thead><tr><th>النوع</th><th class="n">عدد الحركات</th><th class="n">داخل</th><th class="n">خارج</th></tr></thead>
                    <tbody>
                        @foreach ($byType as $row)
                            <tr>
                                <td>{{ $types[$row->type] ?? $row->type }}</td>
                                <td class="n">{{ $row->cnt }}</td>
                                <td class="n">{{ $row->direction === 'in' ? $m($row->total) : '' }}</td>
                                <td class="n">{{ $row->direction === 'out' ? $m($row->total) : '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <section class="erp-card no-print">
        <details>
            <summary class="erp-card-head" style="cursor:pointer"><h2>إعدادات الخزينة</h2></summary>
            <form method="POST" action="{{ route('cash-registers.update', $register) }}" class="x-body x-fields">
                @csrf @method('PUT')
                <div class="x-field"><label for="rn">الاسم</label><input type="text" id="rn" name="name" value="{{ $register->name }}" required maxlength="255"></div>
                <div class="x-field">
                    <label for="rs">الحالة</label>
                    <select id="rs" name="status">
                        <option value="active" @selected(!$off)>نشطة</option>
                        <option value="inactive" @selected($off)>موقوفة (لا تظهر في البيع والورديات)</option>
                    </select>
                </div>
                <div class="x-actions"><button class="x-btn">حفظ</button></div>
            </form>
            <form method="POST" action="{{ route('cash-registers.destroy', $register) }}" class="x-body" style="padding-top:0" onsubmit="return confirm('حذف الخزينة «{{ e($register->name) }}»؟')">
                @csrf @method('DELETE')
                <div class="x-note">الحذف مسموح فقط إذا كان الرصيد صفراً. حوّل رصيدها لخزينة أخرى أولاً. الحركات القديمة تبقى محفوظة.</div>
                <div class="x-actions" style="margin-top:10px"><button class="x-btn is-danger is-sm">حذف الخزينة</button></div>
            </form>
        </details>
    </section>
</div>

@include('cash_registers._dialogs', ['transferTargets' => $others])
@endsection
