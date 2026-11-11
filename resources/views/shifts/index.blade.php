@extends('layouts.app')

@section('title', 'الورديات')

@section('content')
@php $m = fn ($n) => number_format((float) $n, 2); @endphp

<div class="x-page">
    <div class="x-head">
        <div>
            <h2>الورديات</h2>
            <p>افتح وردية على خزينة في بداية الدوام، وفي نهايته عُدّ الصندوق وأدخل المبلغ الموجود فعلاً. النظام يحسب الزيادة أو العجز ويسجله.</p>
        </div>
    </div>

    @include('partials.x-flash')

    @if ($myShift)
        <section class="erp-card">
            <div class="erp-card-head">
                <h2>ورديتك المفتوحة على «{{ $myShift->cashRegister->name ?? '—' }}»</h2>
                <span class="x-badge is-good">مفتوحة منذ {{ $myShift->opened_at->format('H:i') }}، {{ $myShift->opened_at->locale('ar')->diffForHumans() }}</span>
            </div>
            <div class="x-split" style="padding:0 18px 18px">
                @include('shifts._summary', ['summary' => $mySummary])

                <form method="POST" action="{{ route('shifts.close', $myShift) }}" class="erp-card x-body is-top" style="display:grid;gap:12px;background:#F7FAF9" id="closeForm">
                    @csrf
                    <strong style="font-size:15px">إغلاق الوردية</strong>
                    <div class="x-field">
                        <label for="actual">المبلغ الموجود في الصندوق فعلاً</label>
                        <input type="number" step="0.01" min="0" name="actual_balance" id="actual" required inputmode="decimal" placeholder="{{ $m($mySummary['expected']) }}" value="{{ old('actual_balance') }}">
                    </div>
                    <div class="x-note" id="diffNote" hidden></div>
                    <div class="x-field">
                        <label for="notes">ملاحظات (اختياري)</label>
                        <textarea name="notes" id="notes" maxlength="500" placeholder="مثال: سُلّم المبلغ للمدير">{{ old('notes') }}</textarea>
                    </div>
                    <button class="x-btn">إغلاق الوردية</button>
                </form>
            </div>
        </section>
        <script>
        (function () {
            const expected = {{ (float) $mySummary['expected'] }};
            const input = document.getElementById('actual'), note = document.getElementById('diffNote');
            const money = n => Math.abs(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            input.addEventListener('input', () => {
                if (input.value === '') { note.hidden = true; return; }
                const d = Math.round((parseFloat(input.value) - expected) * 100) / 100;
                note.hidden = false;
                note.className = 'x-note' + (d === 0 ? '' : ' is-warn');
                note.textContent = d === 0 ? 'الصندوق مطابق تماماً.' : (d > 0 ? 'زيادة ' + money(d) + ' ₪ عن المتوقع.' : 'عجز ' + money(d) + ' ₪ عن المتوقع.');
            });
            document.getElementById('closeForm').addEventListener('submit', e => {
                if (!confirm('تأكيد إغلاق الوردية؟ سيُضبط رصيد الخزينة على المبلغ المُدخل.')) e.preventDefault();
            });
        })();
        </script>
    @elseif (auth()->user()->can('manage_shifts'))
        <section class="erp-card">
            <div class="erp-card-head"><h2>فتح وردية جديدة</h2></div>
            @if ($registers->isEmpty())
                <div class="x-empty">لا توجد خزائن نشطة. أنشئ خزينة من قسم المحاسبة أولاً.</div>
            @else
                <form method="POST" action="{{ route('shifts.store') }}" class="x-body x-fields">
                    @csrf
                    <div class="x-field">
                        <label for="reg">الخزينة</label>
                        <select name="cash_register_id" id="reg" required>
                            @foreach ($registers as $r)
                                <option value="{{ $r->id }}" @disabled($r->busy)>{{ $r->name }} — رصيدها {{ $m($r->current_balance) }} ₪{{ $r->busy ? ' (عليها وردية مفتوحة)' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="x-field">
                        <label for="onotes">ملاحظة (اختياري)</label>
                        <input type="text" name="notes" id="onotes" maxlength="500" placeholder="مثال: وردية الصباح">
                    </div>
                    <div class="x-actions"><button class="x-btn">فتح الوردية</button></div>
                </form>
                <div class="x-body" style="padding-top:0"><div class="x-note">رصيد الافتتاح يُؤخذ من رصيد الخزينة الحالي. إذا كان المبلغ الفعلي مختلفاً، أغلق الوردية السابقة بالمبلغ الصحيح أولاً.</div></div>
            @endif
        </section>
    @endif

    <section class="erp-card">
        <div class="erp-card-head">
            <h2>{{ $canAll ? 'كل الورديات' : 'وردياتي السابقة' }}</h2>
            <div class="x-tabs no-print">
                <a href="{{ route('shifts.index') }}" class="{{ !request('status') ? 'is-active' : '' }}">الكل</a>
                <a href="{{ route('shifts.index', ['status' => 'open']) }}" class="{{ request('status') === 'open' ? 'is-active' : '' }}">المفتوحة</a>
                <a href="{{ route('shifts.index', ['status' => 'closed']) }}" class="{{ request('status') === 'closed' ? 'is-active' : '' }}">المغلقة</a>
            </div>
        </div>
        @if ($shifts->isEmpty())
            <div class="x-empty">لا توجد ورديات بعد.</div>
        @else
            <div class="x-table-wrap">
                <table class="x-table">
                    <thead><tr><th>#</th><th>الخزينة</th><th>الكاشير</th><th>الفتح</th><th>الإغلاق</th><th class="n">الافتتاحي</th><th class="n">المتوقع</th><th class="n">الفعلي</th><th class="n">الفرق</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($shifts as $s)
                            @php $d = (float) $s->difference; @endphp
                            <tr>
                                <td class="num">{{ $s->id }}</td>
                                <td>{{ $s->cashRegister->name ?? '—' }}</td>
                                <td>{{ $s->user->username ?? '—' }}</td>
                                <td class="muted">{{ $s->opened_at->format('Y-m-d H:i') }}</td>
                                <td class="muted">{{ $s->closed_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                <td class="n">{{ $m($s->opening_balance) }}</td>
                                <td class="n">{{ $s->expected_balance !== null ? $m($s->expected_balance) : '—' }}</td>
                                <td class="n">{{ $s->actual_balance !== null ? $m($s->actual_balance) : '—' }}</td>
                                <td class="n">
                                    @if ($s->isOpen())
                                        <span class="x-badge is-info">مفتوحة</span>
                                    @elseif ($d == 0)
                                        <span class="x-badge is-good">مطابق</span>
                                    @else
                                        <span class="x-badge {{ $d > 0 ? 'is-warn' : 'is-bad' }}">{{ $d > 0 ? 'زيادة' : 'عجز' }} {{ $m(abs($d)) }}</span>
                                    @endif
                                </td>
                                <td><a href="{{ route('shifts.show', $s) }}">التقرير</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="x-pages">{{ $shifts->links() }}</div>
        @endif
    </section>
</div>
@endsection
