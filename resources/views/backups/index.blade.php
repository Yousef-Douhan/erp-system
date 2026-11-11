@extends('layouts.app')

@section('title', 'النسخ الاحتياطية')

@section('content')
@php $kb = fn ($b) => $b >= 1048576 ? number_format($b / 1048576, 1) . ' MB' : number_format($b / 1024, 0) . ' KB'; @endphp

<div class="x-page" style="max-width:1000px">
    <div class="x-head">
        <div>
            <h2>النسخ الاحتياطية</h2>
            <p>نسخة كاملة من قاعدة البيانات تُؤخذ تلقائياً كل يوم الساعة <b class="num">{{ $time }}</b>، ويُحتفظ بآخر <b class="num">{{ $keep }}</b> نسخة. نزّل نسخة بين فترة وأخرى واحفظها خارج الجهاز (فلاشة أو Google Drive).</p>
        </div>
        <form method="POST" action="{{ route('backups.store') }}" class="x-actions" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='جاري النسخ...'">
            @csrf
            <button class="x-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                نسخة الآن
            </button>
        </form>
    </div>

    @include('partials.x-flash')

    @if ($files->isEmpty() || $files->first()['time']->lt(now()->subDays(2)))
        <div class="x-note is-warn">
            {{ $files->isEmpty() ? 'لا توجد أي نسخة احتياطية بعد.' : 'آخر نسخة عمرها أكثر من يومين.' }}
            تأكد أن الجدولة التلقائية تعمل على الجهاز (راجع ملف docs/DEPLOYMENT.md، قسم «المهام المجدولة»).
        </div>
    @endif

    <section class="erp-card">
        @if ($files->isEmpty())
            <div class="x-empty">اضغط «نسخة الآن» لأخذ أول نسخة.</div>
        @else
            <div class="x-table-wrap">
                <table class="x-table">
                    <thead><tr><th>الملف</th><th>التاريخ</th><th class="n">الحجم</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($files as $f)
                            <tr>
                                <td class="num">{{ $f['name'] }}</td>
                                <td class="muted">{{ $f['time']->format('Y-m-d H:i') }} <span class="x-badge">{{ $f['time']->locale('ar')->diffForHumans() }}</span></td>
                                <td class="n">{{ $kb($f['size']) }}</td>
                                <td>
                                    <div class="x-actions" style="justify-content:flex-end">
                                        <a href="{{ route('backups.download', $f['name']) }}" class="x-btn is-light is-sm">تنزيل</a>
                                        <form method="POST" action="{{ route('backups.destroy', $f['name']) }}" onsubmit="return confirm('حذف هذه النسخة نهائياً؟')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="x-btn is-danger is-sm">حذف</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="erp-card x-body is-top">
        <strong style="font-size:15px">الاسترجاع من نسخة</strong>
        <p style="margin:8px 0 0;font-size:13.5px;color:var(--muted);line-height:1.7">
            فك ضغط الملف (‎.gz‎) ببرنامج مثل 7-Zip، ثم من phpMyAdmin افتح قاعدة <b>erp_system</b> واختر «استيراد» وارفع ملف ‎.sql‎.
            الاسترجاع يستبدل كل البيانات الحالية، فخذ نسخة جديدة قبله.
        </p>
    </section>
</div>
@endsection
