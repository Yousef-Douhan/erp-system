{{-- تبويبات التقارير + فلتر الفترة. المتغيرات: $tab، و $from/$to (أو $days للأصناف الراكدة) --}}
<div class="x-head">
    <div>
        <h2>التقارير</h2>
        <p>{{ $subtitle }}
            @if ($tab !== 'slow')
                الفترة: <span class="x-num">{{ $from->toDateString() }}</span>@if ($from->toDateString() !== $to->toDateString()) إلى <span class="x-num">{{ $to->toDateString() }}</span>@endif
            @endif
        </p>
    </div>
    <div class="x-actions">
        <a href="{{ request()->fullUrlWithQuery(array_merge(['export' => 'csv'], $exportParams ?? [])) }}" class="x-btn is-ghost">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m-5-5 5 5 5-5M4 21h16"/></svg>
            تصدير Excel
        </a>
        <button type="button" class="x-btn is-ghost" onclick="window.print()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v8H6z"/></svg>
            طباعة / PDF
        </button>
    </div>
</div>

<div class="x-actions no-print" style="justify-content:space-between">
    <nav class="x-tabs" aria-label="التقارير">
        <a href="{{ route('reports.profit', request()->only(['from', 'to'])) }}" class="{{ $tab === 'profit' ? 'is-active' : '' }}">الأرباح</a>
        <a href="{{ route('reports.employees', request()->only(['from', 'to'])) }}" class="{{ $tab === 'employees' ? 'is-active' : '' }}">مبيعات الموظفين</a>
        <a href="{{ route('reports.slow') }}" class="{{ $tab === 'slow' ? 'is-active' : '' }}">الأصناف الراكدة</a>
        @if (Route::has('reports.income-statement') && auth()->user()->can('manage_accounting'))
            <a href="{{ route('reports.income-statement') }}">قائمة الدخل</a>
        @endif
    </nav>

    @if ($tab !== 'slow')
        @php
            $presets = [
                'اليوم'       => [today(), today()],
                'هذا الشهر'   => [today()->startOfMonth(), today()],
                'الشهر الماضي' => [today()->subMonthNoOverflow()->startOfMonth(), today()->subMonthNoOverflow()->endOfMonth()],
                'هذه السنة'   => [today()->startOfYear(), today()],
            ];
        @endphp
        <form method="GET" class="x-actions">
            @foreach ($presets as $label => [$a, $b])
                <a href="{{ request()->fullUrlWithQuery(['from' => $a->toDateString(), 'to' => $b->toDateString(), 'export' => null, 'page' => null]) }}"
                   class="x-btn is-sm {{ $from->toDateString() === $a->toDateString() && $to->toDateString() === $b->toDateString() ? 'is-light' : 'is-ghost' }}">{{ $label }}</a>
            @endforeach
            <input type="date" name="from" value="{{ $from->toDateString() }}" aria-label="من تاريخ" style="padding:0 10px;min-height:32px !important;height:32px">
            <input type="date" name="to" value="{{ $to->toDateString() }}" aria-label="إلى تاريخ" style="padding:0 10px;min-height:32px !important;height:32px">
            <button class="x-btn is-sm">عرض</button>
        </form>
    @else
        <form method="GET" class="x-actions">
            <label for="days" style="font-size:13px;font-weight:600">لم تُبع منذ</label>
            <select name="days" id="days" onchange="this.form.submit()" style="padding:0 10px;min-height:32px !important;height:32px">
                @foreach ([30, 60, 90, 180, 365] as $d)
                    <option value="{{ $d }}" @selected($days == $d)>{{ $d }} يوماً</option>
                @endforeach
            </select>
        </form>
    @endif
</div>
