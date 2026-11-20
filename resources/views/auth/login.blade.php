@extends('layouts.auth')

@section('title', 'تسجيل الدخول')

@section('content')
    <h2>تسجيل الدخول</h2>
    <p class="lead">أهلاً بك. أدخل اسم المستخدم أو البريد الإلكتروني وكلمة المرور.</p>

    @if (session('status'))
        <div class="alert ok" role="status">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.1V12a10 10 0 1 1-5.9-9.1M22 4 12 14l-3-3"/></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" data-once novalidate>
        @csrf

        <div class="field {{ $errors->has('email') ? 'has-error' : '' }}">
            <label for="email">اسم المستخدم أو البريد الإلكتروني</label>
            <div class="input">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <input id="email" type="text" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                       placeholder="cashier1 / name@example.com" dir="ltr" spellcheck="false" autocapitalize="off"
                       @if ($errors->has('email')) aria-invalid="true" aria-describedby="email-err" @endif>
            </div>
            @error('email')<div class="err" id="email-err">{{ $message }}</div>@enderror
        </div>

        @include('auth._password', [
            'id' => 'password', 'name' => 'password', 'label' => 'كلمة المرور', 'autocomplete' => 'current-password',
            'labelExtra' => Route::has('password.request') ? '<a href="' . e(route('password.request')) . '">نسيت كلمة المرور؟</a>' : '',
        ])

        <div style="margin:4px 0 24px">
            <label class="check"><input type="checkbox" name="remember" value="1" @checked(old('remember'))> تذكرني على هذا الجهاز</label>
        </div>

        <button type="submit" class="btn">
            <span class="spinner" aria-hidden="true"></span>
            <svg class="btn-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l-5-5 5-5M5 12h12"/></svg>
            دخول
        </button>
    </form>

    <div class="divider"></div>
    <div class="help">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
        <span>لا يوجد تسجيل ذاتي. حسابك يُنشئه صاحب الصيدلية أو مدير الفرع من شاشة «المستخدمون». لا تستخدم «تذكرني» على جهاز مشترك.</span>
    </div>
@endsection
