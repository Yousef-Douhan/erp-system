@extends('layouts.app')

@section('title', 'مطابقة الحوالات')

@section('content')
@php
    $m = fn ($n) => number_format((float) $n, 2);
    $mName = fn ($code) => $code ? ($methods[$code] ?? $code) : 'كل الطرق';
    $isImage = fn ($path) => in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true);
    $docLabel = fn ($d) => $d->statement_date->format('Y-m-d') . ' — ' . $mName($d->payment_method) . ' — ' . ($d->note ?: ($d->original_name ?: 'مستند #' . $d->id));
    $pendingRows = $checks->where('status', 'pending');
    $today = today()->toDateString();
@endphp

<style>
    .tc-dialog { position: fixed; inset: 0; margin: auto; height: fit-content; max-height: calc(100vh - 32px); overflow: auto; border: 0; border-radius: var(--radius); padding: 0; width: min(460px, calc(100vw - 32px)); box-shadow: 0 20px 50px rgba(16, 42, 43, .25); }
    .tc-dialog::backdrop { background: rgba(16, 42, 43, .45); }
    .tc-dialog form { display: grid; gap: 14px; padding: 20px; }
    .tc-dialog h3 { margin: 0; font-size: 17px; }
    .tc-dialog .x-actions { justify-content: flex-start; }
    .tc-bar { position: sticky; bottom: 12px; z-index: 5; display: flex; flex-wrap: wrap; align-items: center; gap: 12px; padding: 12px 16px; border-radius: var(--radius); background: var(--ink); color: #fff; box-shadow: 0 10px 30px rgba(16,42,43,.25); }
    .tc-bar[hidden] { display: none; }
    .tc-bar select, .tc-bar input[type=date] { min-height: 36px; border-radius: 9px; border: 0; padding: 0 10px; font: inherit; font-size: 13px; }
    .tc-bar .x-btn { background: #7FD1C7; color: var(--ink); }
    .tc-row-out td { background: #FFF8F7; }
    .tc-day td { background: var(--canvas); font-weight: 700; font-size: 13px; color: var(--muted); }
    .tc-in { color: var(--green); font-weight: 700; }
    .tc-out { color: var(--red); font-weight: 700; }
    .tc-actions { display: flex; gap: 6px; flex-wrap: wrap; }
    .tc-docs { display: grid; gap: 12px; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); padding: 0 18px 18px; }
    .tc-doc { border: 1px solid var(--line); border-radius: 12px; overflow: hidden; background: #fff; display: flex; flex-direction: column; }
    .tc-doc a.thumb { display: block; height: 120px; background: var(--canvas) center/cover no-repeat; position: relative; }
    .tc-doc a.thumb.pdf { display: grid; place-items: center; font-weight: 700; color: var(--red); font-size: 22px; }
    .tc-doc .meta { padding: 9px 10px; font-size: 12.5px; line-height: 1.6; flex: 1; }
    .tc-doc .meta b { display: block; font-size: 13px; }
    .tc-doc .foot { display: flex; justify-content: space-between; align-items: center; padding: 0 10px 9px; font-size: 12px; }
    .tc-upload { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); align-items: end; padding: 0 18px 18px; }
</style>

<div class="x-page">
    <div class="x-head">
        <div>
            <h2>مطابقة الحوالات</h2>
            <p>
                @if ($canUpload && $canVerify)
                    ارفع كشف الحساب وصور الحوالات، ثم طابق كل دفعة بنك/محفظة: <b>وصلت</b> (بتاريخ وصولها) أو <b>لم تصل</b>.
                @elseif ($canUpload)
                    ارفع كشف الحساب وصور الحوالات، وموظف المطابقة يطابق عليها.
                @else
                    افتح المستندات المرفوعة وطابق كل دفعة: <b>وصلت</b> (واختر تاريخ وصولها والمستند) أو <b>لم تصل</b>. أي مطابقة يمكن تعديلها لاحقاً.
                @endif
            </p>
        </div>
    </div>

    @include('partials.x-flash')

    <div class="x-actions no-print" style="justify-content:space-between">
        <nav class="x-tabs" aria-label="العرض">
            <a href="{{ route('transfers.index') }}" class="{{ $view === 'pending' ? 'is-active' : '' }}">بانتظار المطابقة <span class="x-badge {{ $pendingCount ? 'is-warn' : 'is-good' }}" style="margin-inline-start:6px">{{ $pendingCount }}</span></a>
            <a href="{{ route('transfers.index', ['view' => 'day', 'date' => $date->toDateString()]) }}" class="{{ $view === 'day' ? 'is-active' : '' }}">حسب اليوم</a>
        </nav>
        @if ($view === 'day')
            <form method="GET" class="x-actions">
                <input type="hidden" name="view" value="day">
                <a class="x-btn is-ghost is-sm" href="{{ request()->fullUrlWithQuery(['date' => $date->copy()->subDay()->toDateString()]) }}">→ السابق</a>
                <input type="date" name="date" value="{{ $date->toDateString() }}" max="{{ $today }}" onchange="this.form.submit()" aria-label="التاريخ" style="min-height:34px">
                @unless ($date->isToday())<a class="x-btn is-ghost is-sm" href="{{ request()->fullUrlWithQuery(['date' => $date->copy()->addDay()->toDateString()]) }}">التالي ←</a>@endunless
                <select name="status" onchange="this.form.submit()" style="min-height:34px" aria-label="الحالة">
                    <option value="">كل الحالات</option>
                    @foreach (\App\Models\TransferCheck::STATUSES as $k => $l)<option value="{{ $k }}" @selected($status === $k)>{{ $l }}</option>@endforeach
                </select>
            </form>
        @endif
    </div>

    <form method="GET" class="erp-card x-body is-top no-print" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">
        @if ($view === 'day')<input type="hidden" name="view" value="day"><input type="hidden" name="date" value="{{ $date->toDateString() }}">@endif
        @if ($method)<input type="hidden" name="method" value="{{ $method }}">@endif
        <div class="x-field" style="flex:1;min-width:240px;margin:0">
            <label for="tcQ">ابحث بما تراه في كشف الحساب</label>
            <input type="search" id="tcQ" name="q" value="{{ $q }}" placeholder="اسم المحوِّل، رقم الجوال أو الحساب، رقم العملية، أو المبلغ" autofocus>
        </div>
        <button class="x-btn">بحث</button>
        @if ($q)<a href="{{ request()->fullUrlWithQuery(['q' => null]) }}" class="x-btn is-ghost">مسح</a>@endif
    </form>

    {{-- ملخص كل طريقة دفع --}}
    @if ($byMethod->isNotEmpty())
        <div class="x-stats">
            @foreach ($byMethod as $row)
                @php $done = (float) $row->pending == 0 && (float) $row->rejected == 0; @endphp
                <a href="{{ request()->fullUrlWithQuery(['method' => $method === $row->payment_method ? null : $row->payment_method]) }}"
                   class="x-stat {{ $done ? 'is-good' : ((float) $row->rejected > 0 ? 'is-bad' : 'is-warn') }}" style="text-decoration:none;{{ $method === $row->payment_method ? 'outline:2px solid var(--teal)' : '' }}">
                    <span>{{ $mName($row->payment_method) }}</span>
                    <strong class="num">{{ $m($view === 'pending' ? $row->pending : $row->total_in) }}<small>₪</small></strong>
                    <em>
                        @if ($view === 'pending')
                            {{ $row->cnt }} حوالة تنتظر{{ (float) $row->total_out > 0 ? ' · رد للزبائن ' . $m($row->total_out) : '' }}
                        @else
                            وصل {{ $m($row->confirmed) }}
                            @if ((float) $row->pending > 0) · ينتظر {{ $m($row->pending) }} @endif
                            @if ((float) $row->rejected > 0) · لم يصل {{ $m($row->rejected) }} @endif
                            @if ((float) $row->total_out > 0) · رُدّ {{ $m($row->total_out) }} @endif
                        @endif
                    </em>
                </a>
            @endforeach
        </div>
    @endif

    {{-- ================= المستندات ================= --}}
    <section class="erp-card">
        <div class="erp-card-head">
            <h2>كشوف الحساب وصور الحوالات <span class="muted" style="font-weight:500;font-size:13px">{{ $view === 'pending' ? '(آخر 14 يوماً)' : '(' . $date->toDateString() . ')' }}</span></h2>
            @if ($canUpload)<button type="button" class="x-btn is-sm no-print" id="tcUploadToggle">+ رفع مستندات</button>@endif
        </div>

        @if ($canUpload)
            <form method="POST" action="{{ route('transfers.documents.store') }}" enctype="multipart/form-data" class="tc-upload no-print" id="tcUpload" @unless ($errors->has('files') || $errors->has('files.*')) hidden @endunless>
                @csrf
                <div class="x-field"><label for="docDate">تاريخ الكشف / الحوالات</label><input type="date" id="docDate" name="statement_date" value="{{ old('statement_date', $view === 'day' ? $date->toDateString() : $today) }}" max="{{ $today }}" required></div>
                <div class="x-field">
                    <label for="docMethod">الحساب / المحفظة</label>
                    <select id="docMethod" name="payment_method">
                        <option value="">أكثر من حساب</option>
                        @foreach ($methods as $code => $name)@if (!in_array($code, ['cash', 'debt'], true))<option value="{{ $code }}" @selected(old('payment_method') === $code)>{{ $name }}</option>@endif @endforeach
                    </select>
                </div>
                <div class="x-field"><label for="docFiles">الملفات (صور أو PDF، حتى 20)</label><input type="file" id="docFiles" name="files[]" accept="image/*,application/pdf" multiple required></div>
                <div class="x-field"><label for="docNote">ملاحظة (اختياري)</label><input type="text" id="docNote" name="note" maxlength="500" value="{{ old('note') }}" placeholder="مثال: كشف بنك فلسطين صباحاً"></div>
                <div class="x-actions"><button class="x-btn">رفع</button></div>
            </form>
        @endif

        @if ($documents->isEmpty())
            <div class="x-empty">لا توجد مستندات مرفوعة{{ $canUpload ? '. اضغط «رفع مستندات».' : ' بعد. اطلب من صاحب الصيدلية رفع كشف الحساب أو صور الحوالات.' }}</div>
        @else
            <div class="tc-docs">
                @foreach ($documents as $d)
                    <div class="tc-doc">
                        @if ($isImage($d->file_path))
                            <a class="thumb" href="{{ route('transfers.statement', $d) }}" target="_blank" style="background-image:url('{{ route('transfers.statement', $d) }}')" aria-label="فتح المستند"></a>
                        @else
                            <a class="thumb pdf" href="{{ route('transfers.statement', $d) }}" target="_blank">PDF</a>
                        @endif
                        <div class="meta">
                            <b>{{ $mName($d->payment_method) }}</b>
                            <span class="num">{{ $d->statement_date->format('Y-m-d') }}</span>
                            @if ($d->note)<div class="muted">{{ $d->note }}</div>@endif
                        </div>
                        <div class="foot">
                            <span class="x-badge {{ $d->checks_count ? 'is-good' : '' }}">{{ $d->checks_count }} حوالة</span>
                            @if ($canUpload && !$d->checks_count)
                                <form method="POST" action="{{ route('transfers.documents.destroy', $d) }}" onsubmit="return confirm('حذف هذا المستند؟')">@csrf @method('DELETE')<button class="x-btn is-ghost is-sm" style="color:var(--red)">حذف</button></form>
                            @else
                                <span class="muted">{{ $d->user->username ?? '' }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- ================= الحوالات ================= --}}
    <section class="erp-card">
        <div class="erp-card-head">
            <h2>{{ $view === 'pending' ? 'حوالات بانتظار المطابقة (من كل الأيام)' : 'حوالات يوم ' . $date->toDateString() }}</h2>
            <button type="button" class="x-btn is-ghost is-sm no-print" onclick="window.print()">طباعة / PDF</button>
        </div>

        @if ($checks->isEmpty())
            <div class="x-empty">{{ $q ? 'لا توجد حوالة تطابق «' . $q . '»' . ($view === 'pending' ? ' بين المنتظرة. جرّب «حسب اليوم» أو جزءاً من الاسم/الرقم.' : '.') : ($view === 'pending' ? 'ممتاز، كل الحوالات مُطابقة.' : 'لا توجد دفعات بنك أو محفظة في هذا اليوم.') }}</div>
        @else
            <div class="x-table-wrap">
                <table class="x-table">
                    <thead>
                        <tr>
                            <th class="no-print" style="width:36px">@if ($canVerify && $pendingRows->isNotEmpty())<input type="checkbox" id="tcAll" aria-label="تحديد كل المنتظرة">@endif</th>
                            <th>الوقت</th><th>الفاتورة</th><th>المحوِّل</th><th>الطريقة</th><th class="n">المبلغ</th><th>الكاشير</th><th>المطابقة</th>@if ($canVerify)<th class="no-print"></th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @php $lastDay = null; @endphp
                        @foreach ($checks as $c)
                            @php
                                $day = $c->created_at->toDateString();
                                $payload = ['url' => route('transfers.update', $c), 'confirm' => route('transfers.confirm', $c), 'reject' => route('transfers.reject', $c),
                                    'label' => $mName($c->payment_method) . ' — ' . $m($c->amount) . ' ₪' . ($c->sale ? ' — ' . $c->sale->invoice_number : ''),
                                    'min' => $day, 'status' => $c->status, 'received' => $c->received_on?->toDateString(), 'doc' => $c->statement_id, 'note' => $c->note];
                            @endphp
                            @if ($view === 'pending' && $day !== $lastDay)
                                @php $lastDay = $day; @endphp
                                <tr class="tc-day"><td colspan="9">{{ $day }} {{ $day === $today ? '(اليوم)' : '— منذ ' . $c->created_at->copy()->startOfDay()->diffInDays(today()) . ' يوم' }}</td></tr>
                            @endif
                            <tr class="{{ $c->direction === 'out' ? 'tc-row-out' : '' }}">
                                <td class="no-print">
                                    @if ($canVerify && $c->status === 'pending')
                                        <input type="checkbox" class="tc-pick" form="bulkForm" name="ids[]" value="{{ $c->id }}" data-amount="{{ (float) $c->amount }}" aria-label="تحديد">
                                    @endif
                                </td>
                                <td class="muted">{{ $c->created_at->format('H:i') }}</td>
                                <td>
                                    @if ($c->sale)<a href="{{ route('sales.show', $c->sale_id) }}" class="num">{{ $c->sale->invoice_number }}</a>@else — @endif
                                    <div class="muted" style="font-size:12px">{{ \App\Models\TransferCheck::SOURCES[$c->source] ?? $c->source }}</div>
                                </td>
                                <td>
                                    <b>{{ $c->payer_name ?: ($c->customer->name ?? '—') }}</b>
                                    @if ($c->wallet_phone)<div class="num" dir="ltr" style="text-align:right;font-size:12.5px">📱 {{ $c->wallet_phone }}</div>@endif
                                    @if ($c->payer_account)<div class="num muted" style="font-size:12px">حساب: {{ $c->payer_account }}</div>@endif
                                    @if ($c->transfer_ref)<div class="num muted" style="font-size:12px">عملية: {{ $c->transfer_ref }}</div>@endif
                                    @if ($c->customer && $c->payer_name && $c->payer_name !== $c->customer->name)<div class="muted" style="font-size:12px">العميل: {{ $c->customer->name }}</div>@endif
                                </td>
                                <td>{{ $mName($c->payment_method) }}</td>
                                <td class="n {{ $c->direction === 'out' ? 'tc-out' : 'tc-in' }}">{{ $c->direction === 'out' ? '− ' : '' }}{{ $m($c->amount) }}</td>
                                <td class="muted">{{ $c->user->username ?? '—' }}</td>
                                <td>
                                    <span class="x-badge {{ $c->status === 'confirmed' ? 'is-good' : ($c->status === 'rejected' ? 'is-bad' : 'is-warn') }}">
                                        {{ $c->status === 'confirmed' ? ($c->direction === 'out' ? 'حُوِّلت ✓' : 'وصلت ✓') : \App\Models\TransferCheck::STATUSES[$c->status] }}
                                    </span>
                                    @if ($c->status === 'confirmed' && $c->received_on)
                                        <div style="font-size:12px" class="{{ $c->arrivedLate() ? '' : 'muted' }}">
                                            @if ($c->arrivedLate())<b style="color:var(--amber)">وصلت متأخرة: {{ $c->received_on->format('Y-m-d') }}</b>@else بتاريخ {{ $c->received_on->format('Y-m-d') }} @endif
                                        </div>
                                    @endif
                                    @if ($c->checker)<div class="muted" style="font-size:12px">{{ $c->checker->username }} · {{ $c->checked_at?->format('m-d H:i') }}</div>@endif
                                    @if ($c->note)<div style="font-size:12px;color:{{ $c->status === 'rejected' ? 'var(--red)' : 'var(--muted)' }}">{{ $c->note }}</div>@endif
                                    @if ($c->statement)<a href="{{ route('transfers.statement', $c->statement_id) }}" target="_blank" style="font-size:12px;display:block">📄 المستند</a>@endif
                                    @if ($c->proof_path)<a href="{{ route('transfers.proof', $c) }}" target="_blank" style="font-size:12px;display:block">📎 صورة الحوالة</a>@endif
                                </td>
                                @if ($canVerify)
                                    <td class="no-print">
                                        <div class="tc-actions">
                                            @if ($c->status === 'pending')
                                                <button type="button" class="x-btn is-sm js-confirm" data-c='@json($payload)'>{{ $c->direction === 'out' ? 'حُوِّلت ✓' : 'وصلت ✓' }}</button>
                                                <button type="button" class="x-btn is-danger is-sm js-reject" data-c='@json($payload)'>{{ $c->direction === 'out' ? 'لم تُحوَّل' : 'لم تصل' }}</button>
                                            @else
                                                <button type="button" class="x-btn is-ghost is-sm js-edit" data-c='@json($payload)'>تعديل</button>
                                            @endif
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    @if ($canVerify && $pendingRows->isNotEmpty())
        <form method="POST" action="{{ route('transfers.bulk') }}" id="bulkForm" class="tc-bar no-print" hidden>
            @csrf
            <b id="tcCount">0 محددة</b>
            <span id="tcSum" class="num"></span>
            <label style="font-size:13px;display:flex;align-items:center;gap:6px">وصلت بتاريخ <input type="date" name="received_on" value="{{ $today }}" max="{{ $today }}"></label>
            <label style="font-size:13px;display:flex;align-items:center;gap:6px">المستند
                <select name="statement_id"><option value="">—</option>@foreach ($docOptions as $d)<option value="{{ $d->id }}">{{ $docLabel($d) }}</option>@endforeach</select>
            </label>
            <button class="x-btn" style="margin-inline-start:auto">تأكيد المحدد — وصلت ✓</button>
        </form>
    @endif
</div>

@if ($canVerify)
<dialog class="tc-dialog" id="matchDialog">
    <form method="POST" id="matchForm">
        @csrf
        <input type="hidden" name="_method" id="matchMethod" value="POST">
        <h3 id="matchTitle">مطابقة الحوالة</h3>
        <p class="muted" id="matchLabel" style="margin:-8px 0 0"></p>

        <div class="x-field" id="statusField">
            <label for="matchStatus">الحالة</label>
            <select name="status" id="matchStatus">
                <option value="confirmed">وصلت ✓</option>
                <option value="rejected">لم تصل</option>
                <option value="pending">إرجاع إلى «بانتظار المطابقة»</option>
            </select>
        </div>
        <div id="confirmFields" style="display:grid;gap:14px">
            <div class="x-field"><label for="matchDate">تاريخ وصولها</label><input type="date" name="received_on" id="matchDate" max="{{ $today }}"><small class="muted">إذا وصلت في يوم لاحق اختر ذلك اليوم.</small></div>
            <div class="x-field">
                <label for="matchDoc">ظهرت في مستند (اختياري)</label>
                <select name="statement_id" id="matchDoc"><option value="">—</option>@foreach ($docOptions as $d)<option value="{{ $d->id }}">{{ $docLabel($d) }}</option>@endforeach</select>
            </div>
        </div>
        <div class="x-field"><label for="matchNote" id="matchNoteLabel">ملاحظة (اختياري)</label><input type="text" name="note" id="matchNote" maxlength="500" placeholder="مثال: رقم العملية 4471023"></div>
        <div class="x-note" id="rejectHint" hidden>ستبقى الفاتورة كما هي. تابع المبلغ مع الزبون. إذا وصلت الحوالة لاحقاً اضغط «تعديل» واختر «وصلت».</div>
        <div class="x-actions"><button class="x-btn" id="matchSubmit">حفظ</button><button type="button" class="x-btn is-ghost" data-close>إلغاء</button></div>
    </form>
</dialog>
@endif

<script>
(function () {
    const up = document.getElementById('tcUploadToggle');
    if (up) up.addEventListener('click', () => { const f = document.getElementById('tcUpload'); f.hidden = !f.hidden; });

    const dlg = document.getElementById('matchDialog');
    if (dlg) {
        const $ = id => document.getElementById(id);
        dlg.querySelector('[data-close]').addEventListener('click', () => dlg.close());
        const sync = () => {
            const s = $('matchStatus').value;
            $('confirmFields').style.display = s === 'confirmed' ? 'grid' : 'none';
            $('rejectHint').hidden = s !== 'rejected';
            $('matchNote').required = s === 'rejected';
            $('matchNoteLabel').textContent = s === 'rejected' ? 'ماذا حدث؟ (مطلوب)' : 'ملاحظة (اختياري)';
            $('matchSubmit').className = 'x-btn' + (s === 'rejected' ? ' is-danger' : '');
        };
        $('matchStatus').addEventListener('change', sync);

        const open = (c, mode) => {
            const form = $('matchForm');
            $('matchLabel').textContent = c.label;
            $('matchDate').min = c.min;
            $('matchDate').value = c.received || '{{ $today }}';
            $('matchDoc').value = c.doc || '';
            $('matchNote').value = c.note || '';
            if (mode === 'edit') {
                form.action = c.url; $('matchMethod').value = 'PUT'; $('statusField').style.display = '';
                $('matchStatus').value = c.status; $('matchTitle').textContent = 'تعديل المطابقة';
            } else {
                form.action = mode === 'reject' ? c.reject : c.confirm; $('matchMethod').value = 'POST'; $('statusField').style.display = 'none';
                $('matchStatus').value = mode === 'reject' ? 'rejected' : 'confirmed';
                $('matchTitle').textContent = mode === 'reject' ? 'الحوالة لم تصل' : 'تأكيد وصول الحوالة';
            }
            sync();
            dlg.showModal();
        };
        document.querySelectorAll('.js-confirm').forEach(b => b.addEventListener('click', () => open(JSON.parse(b.dataset.c), 'confirm')));
        document.querySelectorAll('.js-reject').forEach(b => b.addEventListener('click', () => open(JSON.parse(b.dataset.c), 'reject')));
        document.querySelectorAll('.js-edit').forEach(b => b.addEventListener('click', () => open(JSON.parse(b.dataset.c), 'edit')));
    }

    const bar = document.getElementById('bulkForm');
    if (!bar) return;
    const picks = [...document.querySelectorAll('.tc-pick')];
    const money = n => n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const refresh = () => {
        const sel = picks.filter(p => p.checked);
        bar.hidden = sel.length === 0;
        document.getElementById('tcCount').textContent = sel.length + ' محددة';
        document.getElementById('tcSum').textContent = 'المجموع ' + money(sel.reduce((s, p) => s + parseFloat(p.dataset.amount), 0)) + ' ₪';
    };
    picks.forEach(p => p.addEventListener('change', refresh));
    const all = document.getElementById('tcAll');
    if (all) all.addEventListener('change', () => { picks.forEach(p => p.checked = all.checked); refresh(); });
})();
</script>
@endsection
