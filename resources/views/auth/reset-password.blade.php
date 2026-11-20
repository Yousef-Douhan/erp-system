@extends('layouts.auth')

@section('title', 'تعيين كلمة مرور جديدة')

@section('content')
    <span class="step-badge">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
        الخطوة 2 من 2
    </span>
    <h2>كلمة مرور جديدة</h2>
    <p class="lead">اختر كلمة مرور قوية لا تستخدمها في مكان آخر. بعد الحفظ ستعود لصفحة الدخول.</p>

    <form method="POST" action="{{ route('password.store') }}" data-once novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="field {{ $errors->has('email') ? 'has-error' : '' }}">
            <label for="email">البريد الإلكتروني</label>
            <div class="input">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 6 12 13 2 6M2 6h20v12H2z"/></svg>
                <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autocomplete="username" dir="ltr"
                       @if ($request->email && !$errors->has('email')) readonly @endif
                       @if ($errors->has('email')) aria-invalid="true" aria-describedby="email-err" @endif>
            </div>
            @error('email')<div class="err" id="email-err">{{ $message }}</div>@enderror
        </div>

        @include('auth._password', ['id' => 'password', 'name' => 'password', 'label' => 'كلمة المرور الجديدة', 'autocomplete' => 'new-password'])
        <div class="meter" id="meter" data-s="0" aria-hidden="true" style="margin-top:-10px"><i></i><i></i><i></i><i></i></div>
        <div class="hint" id="meterText" style="margin:7px 0 18px" aria-live="polite">8 أحرف على الأقل. الأفضل خلط أحرف وأرقام ورموز.</div>

        @include('auth._password', ['id' => 'password_confirmation', 'name' => 'password_confirmation', 'label' => 'تأكيد كلمة المرور', 'autocomplete' => 'new-password'])
        <div class="err" id="matchErr" style="display:none;margin:-10px 0 18px">كلمتا المرور غير متطابقتين.</div>

        <button type="submit" class="btn" style="margin-top:6px">
            <span class="spinner" aria-hidden="true"></span>
            <svg class="btn-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
            حفظ كلمة المرور
        </button>
    </form>

    <div class="below"><a href="{{ route('login') }}">→ العودة لتسجيل الدخول</a></div>
@endsection

@push('scripts')
<script>
(function () {
    var p = document.getElementById('password'), c = document.getElementById('password_confirmation');
    var meter = document.getElementById('meter'), text = document.getElementById('meterText'), match = document.getElementById('matchErr');
    var labels = ['8 أحرف على الأقل. الأفضل خلط أحرف وأرقام ورموز.', 'ضعيفة', 'متوسطة', 'جيدة', 'قوية'];
    function score(v) {
        if (v.length < 8) return v.length ? 1 : 0;
        var s = 1;
        if (/[a-z]/.test(v) && /[A-Z]/.test(v)) s++;
        if (/\d/.test(v)) s++;
        if (/[^A-Za-z0-9]/.test(v) || v.length >= 12) s++;
        return Math.min(4, s);
    }
    p.addEventListener('input', function () {
        var s = score(p.value);
        meter.setAttribute('data-s', s);
        text.textContent = s ? 'قوة كلمة المرور: ' + labels[s] + (p.value.length < 8 ? ' (8 أحرف على الأقل)' : '') : labels[0];
        checkMatch();
    });
    function checkMatch() { match.style.display = c.value && c.value !== p.value ? 'block' : 'none'; }
    c.addEventListener('input', checkMatch);
})();
</script>
@endpush
