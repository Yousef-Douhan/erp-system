<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        return [
            // الحقل اسمه email لكنه يقبل اسم المستخدم أو البريد الإلكتروني
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required'    => 'اكتب اسم المستخدم أو البريد الإلكتروني.',
            'password.required' => 'اكتب كلمة المرور.',
        ];
    }

    /** عمود الدخول: البريد إذا كان المُدخل بريداً، وإلا اسم المستخدم */
    private function loginColumn(): string
    {
        return filter_var(trim((string) $this->input('email')), FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
    }

    private function credentials(): array
    {
        return [$this->loginColumn() => trim((string) $this->input('email')), 'password' => (string) $this->input('password')];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        // الحساب الموقوف: رسالة واضحة بدل "بيانات الدخول غير صحيحة"
        $user = \App\Models\User::where($this->loginColumn(), trim((string) $this->input('email')))->first();
        if ($user && ! $user->is_active && \Illuminate\Support\Facades\Hash::check((string) $this->input('password'), $user->password)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'هذا الحساب موقوف. تواصل مع مدير النظام لإعادة تفعيله.',
            ]);
        }

        // منع المستخدمين الموقوفين (is_active = false) من تسجيل الدخول
        if (! Auth::attempt($this->credentials() + ['is_active' => true], $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'اسم المستخدم/البريد أو كلمة المرور غير صحيحة.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => 'محاولات دخول كثيرة. حاول مرة أخرى بعد ' . ($seconds > 60 ? ceil($seconds / 60) . ' دقيقة' : $seconds . ' ثانية') . '.',
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
