@extends('layouts.auth')

@section('title', 'استعادة كلمة المرور')

@section('content')
    <span class="step-badge">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v16H4z" opacity="0"/><path d="M22 6 12 13 2 6M2 6h20v12H2z"/></svg>
        الخطوة 1 من 2
    </span>
    <h2>نسيت كلمة المرور؟</h2>
    <p class="lead">اكتب البريد الإلكتروني المسجّل على حسابك، وسنرسل لك رابطاً لتعيين كلمة مرور جديدة. الرابط صالح لمدة {{ config('auth.passwords.users.expire', 60) }} دقيقة.</p>

    @if (session('status'))
        <div class="alert ok" role="status">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.1V12a10 10 0 1 1-5.9-9.1M22 4 12 14l-3-3"/></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" data-once novalidate>
        @csrf
        <div class="field {{ $errors->has('email') ? 'has-error' : '' }}">
            <label for="email">البريد الإلكتروني</label>
            <div class="input">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 6 12 13 2 6M2 6h20v12H2z"/></svg>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                       placeholder="name@example.com" dir="ltr" spellcheck="false" autocapitalize="off"
                       @if ($errors->has('email')) aria-invalid="true" aria-describedby="email-err" @endif>
            </div>
            @error('email')<div class="err" id="email-err">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="btn" style="margin-top:6px">
            <span class="spinner" aria-hidden="true"></span>
            <svg class="btn-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
            أرسل رابط الاستعادة
        </button>
    </form>

    <div class="below"><a href="{{ route('login') }}">→ العودة لتسجيل الدخول</a></div>

    <div class="divider"></div>
    <div class="help">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
        <span>لم تصلك الرسالة خلال دقائق؟ افحص مجلد الرسائل غير المرغوبة (Spam)، أو اطلب من صاحب الصيدلية تغيير كلمة المرور لك من شاشة «المستخدمون».</span>
    </div>
@endsection
