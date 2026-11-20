<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    /**
     * Display the password reset view.
     */
    public function create(Request $request): View
    {
        return view('auth.reset-password', ['request' => $request]);
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'email.required'     => 'اكتب البريد الإلكتروني.',
            'email.email'        => 'صيغة البريد الإلكتروني غير صحيحة.',
            'password.required'  => 'اكتب كلمة المرور الجديدة.',
            'password.confirmed' => 'كلمتا المرور غير متطابقتين.',
            'password.min'       => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل.',
        ]);

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        // If the password was successfully reset, we will redirect the user back to
        // the application's home authenticated view. If there is an error we can
        // redirect them back to where they came from with their error message.
        $messages = [
            Password::INVALID_TOKEN   => 'رابط الاستعادة غير صالح أو انتهت صلاحيته. اطلب رابطاً جديداً.',
            Password::INVALID_USER    => 'رابط الاستعادة غير صالح لهذا البريد. اطلب رابطاً جديداً.',
            Password::RESET_THROTTLED => 'محاولات كثيرة. انتظر دقيقة ثم حاول مرة أخرى.',
        ];

        return $status == Password::PASSWORD_RESET
                    ? redirect()->route('login')->with('status', 'تم تغيير كلمة المرور بنجاح. سجّل دخولك بكلمة المرور الجديدة.')
                    : back()->withInput($request->only('email'))
                            ->withErrors(['email' => $messages[$status] ?? 'تعذّر تغيير كلمة المرور. اطلب رابطاً جديداً.']);
    }
}
