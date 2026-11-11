@extends('layouts.app')

@section('title', 'سلة المحذوفات')

@section('content')
<div class="x-page">
    <div class="x-head">
        <div>
            <h2>سلة المحذوفات</h2>
            <p>الحذف في النظام لا يمسح البيانات: كل ما يُحذف ينتقل إلى هنا ويمكن استرجاعه بضغطة. الفواتير والمشتريات والمصروفات المحذوفة تبقى محفوظة في قاعدة البيانات للمراجعة.</p>
        </div>
    </div>

    @include('partials.x-flash')

    <section class="erp-card">
        <div class="x-body is-top no-print">
            <nav class="x-tabs">
                @foreach ($types as $key => $t)
                    <a href="{{ route('trash.index', ['type' => $key]) }}" class="{{ $key === $type ? 'is-active' : '' }}">
                        {{ $t['label'] }}
                        @if ($counts[$key] > 0)<span class="x-badge num" style="margin-inline-start:6px">{{ $counts[$key] }}</span>@endif
                    </a>
                @endforeach
            </nav>

            <form method="GET" class="x-fields" style="margin-top:12px">
                <input type="hidden" name="type" value="{{ $type }}">
                <div class="x-field"><label for="q">بحث بالاسم</label><input type="search" id="q" name="q" value="{{ $search }}" placeholder="اكتب جزءاً من الاسم"></div>
                <div class="x-actions"><button class="x-btn">بحث</button>@if ($search !== '')<a href="{{ route('trash.index', ['type' => $type]) }}" class="x-btn is-ghost">مسح</a>@endif</div>
            </form>
        </div>

        @if ($rows->isEmpty())
            <div class="x-empty">لا توجد {{ $types[$type]['label'] }} محذوفة{{ $search !== '' ? ' مطابقة' : '' }}.</div>
        @else
            <div class="x-table-wrap" style="margin-top:12px">
                <table class="x-table">
                    <thead><tr><th>الاسم</th><th>تاريخ الحذف</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($rows as $r)
                            <tr>
                                <td>
                                    <strong>{{ $r['title'] ?: '—' }}</strong>
                                    @if ($r['sub'])<div class="muted num" style="font-size:12.5px">{{ $r['sub'] }}</div>@endif
                                </td>
                                <td class="muted">
                                    <span class="num">{{ $r['deleted_at']->format('Y-m-d H:i') }}</span>
                                    <span class="x-badge">{{ $r['deleted_at']->locale('ar')->diffForHumans() }}</span>
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('trash.restore', [$type, $r['id']]) }}" class="x-actions" style="justify-content:flex-end" onsubmit="return confirm('استرجاع «{{ e($r['title']) }}»؟')">
                                        @csrf
                                        <button class="x-btn is-light is-sm">استرجاع</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="x-pages">{{ $items->links() }}</div>
        @endif
    </section>
</div>
@endsection
