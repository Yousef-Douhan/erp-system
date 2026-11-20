<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') — {{ config('app.name', 'نظام الصيدلية') }}</title>
    @include('partials.pwa')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #102A2B; --ink-2: #183A3B; --teal: #0F5C5A; --teal-strong: #0B4746; --teal-soft: #E5F1EF;
            --mint: #7FD1C7; --line: #D8E1DF; --muted: #5C6E6C; --canvas: #F4F7F6; --paper: #FFFFFF;
            --red: #B42318; --red-soft: #FCEBE9; --green: #1F7A4D; --green-soft: #E4F4EA; --amber: #9A5A06; --amber-soft: #FCF1DF;
            --font: 'IBM Plex Sans Arabic', system-ui, -apple-system, 'Segoe UI', Tahoma, sans-serif;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body { font-family: var(--font); color: var(--ink); background: var(--canvas); -webkit-font-smoothing: antialiased; }
        a { color: var(--teal); }
        :focus-visible { outline: 3px solid #5FB3AE; outline-offset: 2px; }

        .auth { min-height: 100vh; display: grid; grid-template-columns: minmax(380px, 46%) 1fr; }

        /* ---------- لوحة الهوية ---------- */
        .auth-brand {
            position: relative; overflow: hidden; color: #E6F2F0;
            background: radial-gradient(120% 90% at 100% 0%, #1E5B59 0%, transparent 55%), linear-gradient(160deg, var(--ink-2) 0%, var(--ink) 70%);
            padding: 44px 52px; display: flex; flex-direction: column; justify-content: space-between; gap: 40px;
        }
        .auth-brand::before {
            /* نقش صليب طبي خفيف */
            content: ""; position: absolute; inset: 0; opacity: .06; pointer-events: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='56' height='56'%3E%3Cpath d='M24 18h8v6h6v8h-6v6h-8v-6h-6v-8h6z' fill='%23ffffff'/%3E%3C/svg%3E");
        }
        .brand-top { position: relative; display: flex; align-items: center; gap: 12px; }
        .brand-mark { width: 44px; height: 44px; border-radius: 12px; background: var(--mint); color: var(--ink); display: grid; place-items: center; flex: none; }
        .brand-mark svg { width: 22px; height: 22px; }
        .brand-name { font-weight: 700; font-size: 17px; line-height: 1.3; }
        .brand-name small { display: block; font-weight: 500; font-size: 12.5px; color: #9FC3BF; }

        .brand-mid { position: relative; max-width: 470px; }
        .brand-mid h1 { font-size: clamp(28px, 3vw, 38px); line-height: 1.35; margin: 0 0 14px; font-weight: 700; color: #fff; }
        .brand-mid h1 em { font-style: normal; color: var(--mint); }
        .brand-mid p { margin: 0 0 28px; color: #B7D3CF; font-size: 15px; line-height: 1.9; }
        .brand-feats { list-style: none; padding: 0; margin: 0; display: grid; gap: 12px; }
        .brand-feats li { display: flex; align-items: center; gap: 12px; font-size: 14.5px; color: #D5E8E5; }
        .brand-feats .ic { width: 34px; height: 34px; border-radius: 10px; background: rgba(127, 209, 199, .12); border: 1px solid rgba(127, 209, 199, .22); display: grid; place-items: center; flex: none; color: var(--mint); }
        .brand-feats .ic svg { width: 17px; height: 17px; }
        .brand-foot { position: relative; font-size: 12.5px; color: #87AAA6; }

        /* ---------- النموذج ---------- */
        .auth-main { display: flex; align-items: center; justify-content: center; padding: 40px 24px; }
        .auth-card { width: 100%; max-width: 420px; }
        .auth-card h2 { margin: 0 0 6px; font-size: 26px; font-weight: 700; }
        .auth-card .lead { margin: 0 0 28px; color: var(--muted); font-size: 14.5px; line-height: 1.8; }
        .step-badge { display: inline-flex; align-items: center; gap: 8px; margin-bottom: 18px; padding: 6px 12px; border-radius: 999px; background: var(--teal-soft); color: var(--teal); font-size: 12.5px; font-weight: 600; }
        .step-badge svg { width: 15px; height: 15px; }

        .field { margin-bottom: 18px; }
        .field label { display: block; font-size: 13.5px; font-weight: 600; margin-bottom: 7px; }
        .field-row { display: flex; align-items: baseline; justify-content: space-between; gap: 10px; }
        .field-row a { font-size: 13px; font-weight: 600; text-decoration: none; }
        .field-row a:hover { text-decoration: underline; }
        .input { position: relative; }
        .input > svg { position: absolute; inset-inline-start: 14px; top: 50%; transform: translateY(-50%); width: 18px; height: 18px; color: #8AA09D; pointer-events: none; }
        .input input {
            width: 100%; height: 50px; border: 1.5px solid var(--line); border-radius: 12px; background: var(--paper);
            padding: 0 46px; font: inherit; font-size: 15px; color: var(--ink); transition: border-color .15s, box-shadow .15s;
        }
        .input input[dir="ltr"] { text-align: right; }
        .input input::placeholder { color: #9AAEAB; }
        .input input:focus { outline: none; border-color: var(--teal); box-shadow: 0 0 0 4px rgba(15, 92, 90, .12); }
        .input input[readonly] { background: var(--canvas); color: var(--muted); }
        .field.has-error .input input { border-color: var(--red); }
        .toggle-pass { position: absolute; inset-inline-end: 6px; top: 50%; transform: translateY(-50%); width: 38px; height: 38px; border: 0; border-radius: 9px; background: transparent; color: #6E8481; cursor: pointer; display: grid; place-items: center; }
        .toggle-pass:hover { background: var(--canvas); color: var(--ink); }
        .toggle-pass svg { width: 19px; height: 19px; }
        .hint { margin-top: 7px; font-size: 12.5px; color: var(--muted); }
        .err { margin-top: 7px; font-size: 13px; color: var(--red); font-weight: 500; }
        .caps { display: none; margin-top: 7px; font-size: 12.5px; color: var(--amber); font-weight: 600; }
        .caps.on { display: block; }

        .check { display: inline-flex; align-items: center; gap: 9px; font-size: 14px; color: var(--ink); cursor: pointer; user-select: none; }
        .check input { width: 18px; height: 18px; accent-color: var(--teal); margin: 0; }

        .btn { width: 100%; height: 52px; border: 0; border-radius: 12px; background: var(--teal); color: #fff; font: inherit; font-size: 15.5px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 10px; transition: background .15s, transform .05s; }
        .btn:hover { background: var(--teal-strong); }
        .btn:active { transform: translateY(1px); }
        .btn[disabled] { opacity: .7; cursor: progress; }
        .btn svg { width: 18px; height: 18px; }
        .spinner { width: 18px; height: 18px; border: 2.5px solid rgba(255,255,255,.35); border-top-color: #fff; border-radius: 50%; animation: spin .7s linear infinite; display: none; }
        .btn.is-loading .spinner { display: inline-block; }
        .btn.is-loading .btn-ic { display: none; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .alert { display: flex; gap: 10px; align-items: flex-start; padding: 13px 14px; border-radius: 12px; font-size: 14px; line-height: 1.7; margin-bottom: 20px; }
        .alert svg { width: 19px; height: 19px; flex: none; margin-top: 2px; }
        .alert.ok { background: var(--green-soft); color: var(--green); }
        .alert.bad { background: var(--red-soft); color: var(--red); }
        .alert.info { background: var(--teal-soft); color: var(--teal-strong); }

        .below { margin-top: 22px; text-align: center; font-size: 14px; color: var(--muted); }
        .below a { font-weight: 600; text-decoration: none; }
        .below a:hover { text-decoration: underline; }
        .divider { height: 1px; background: var(--line); margin: 26px 0 18px; }
        .help { font-size: 13px; color: var(--muted); line-height: 1.8; display: flex; gap: 10px; }
        .help svg { width: 18px; height: 18px; flex: none; color: #8AA09D; margin-top: 3px; }

        .meter { display: grid; grid-template-columns: repeat(4, 1fr); gap: 5px; margin-top: 9px; }
        .meter i { height: 5px; border-radius: 3px; background: var(--line); transition: background .2s; }
        .meter[data-s="1"] i:nth-child(-n+1) { background: var(--red); }
        .meter[data-s="2"] i:nth-child(-n+2) { background: var(--amber); }
        .meter[data-s="3"] i:nth-child(-n+3) { background: #4C9A6A; }
        .meter[data-s="4"] i { background: var(--green); }

        .mobile-brand { display: none; }

        @media (max-width: 900px) {
            .auth { grid-template-columns: 1fr; }
            .auth-brand { display: none; }
            .mobile-brand { display: flex; align-items: center; gap: 10px; margin-bottom: 28px; font-weight: 700; font-size: 16px; }
            .mobile-brand .brand-mark { width: 38px; height: 38px; background: var(--teal); color: #fff; }
            .auth-main { align-items: flex-start; padding: 32px 16px; }
        }
        @media (prefers-reduced-motion: reduce) { * { transition: none !important; animation: none !important; } }
    </style>
</head>
<body>
@php
    $appName = config('app.name') && config('app.name') !== 'Laravel' ? config('app.name') : 'نظام الصيدلية';
    $mark = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>';
@endphp
<div class="auth">
    <aside class="auth-brand" aria-hidden="true">
        <div class="brand-top">
            <span class="brand-mark">{!! $mark !!}</span>
            <span class="brand-name">{{ $appName }}<small>إدارة الصيدليات والفروع</small></span>
        </div>

        <div class="brand-mid">
            <h1>صيدليتك كلها<br><em>في شاشة واحدة.</em></h1>
            <p>البيع والمخزون والصلاحية والحسابات، لكل فروعك، بدقة تعرف معها أين ذهب كل شيكل وكل علبة.</p>
            <ul class="brand-feats">
                <li><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v10H4zM8 18h8M12 14v4"/></svg></span>نقطة بيع سريعة بالباركود ومحافظ إلكترونية</li>
                <li><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20zM12 6v6l4 2"/></svg></span>تنبيهات انتهاء الصلاحية ونقص المخزون</li>
                <li><span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4h18v16H3zM12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6z"/></svg></span>خزائن وورديات وحسابات موردين مضبوطة</li>
            </ul>
        </div>

        <div class="brand-foot">© {{ date('Y') }} يوسف دوحان. جميع الحقوق محفوظة.</div>
    </aside>

    <main class="auth-main">
        <div class="auth-card">
            <div class="mobile-brand"><span class="brand-mark">{!! $mark !!}</span>{{ $appName }}</div>
            @yield('content')
        </div>
    </main>
</div>

<script>
(function () {
    // إظهار/إخفاء كلمة المرور
    document.querySelectorAll('[data-toggle-pass]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.getAttribute('data-toggle-pass'));
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.setAttribute('aria-label', show ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور');
            btn.querySelector('.eye-on').style.display = show ? 'none' : '';
            btn.querySelector('.eye-off').style.display = show ? '' : 'none';
            input.focus();
        });
    });

    // تنبيه Caps Lock
    document.querySelectorAll('input[type="password"]').forEach(function (input) {
        var note = document.getElementById(input.id + '-caps');
        if (!note) return;
        var check = function (e) { if (e.getModifierState) note.classList.toggle('on', e.getModifierState('CapsLock')); };
        input.addEventListener('keydown', check);
        input.addEventListener('keyup', check);
        input.addEventListener('blur', function () { note.classList.remove('on'); });
    });

    // منع الضغط المزدوج على زر الإرسال
    document.querySelectorAll('form[data-once]').forEach(function (form) {
        form.addEventListener('submit', function () {
            var btn = form.querySelector('button[type="submit"]');
            if (btn) { btn.disabled = true; btn.classList.add('is-loading'); }
        });
    });
})();
</script>
@stack('scripts')
</body>
</html>
