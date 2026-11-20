<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ], [
            'email.required' => 'اكتب البريد الإلكتروني.',
            'email.email'    => 'صيغة البريد الإلكتروني غير صحيحة.',
        ]);

        // الحسابات الموقوفة لا تستلم رابطاً (لكن نعرض نفس الرسالة)
        $email = strtolower(trim($request->input('email')));
        $active = \App\Models\User::where('email', $email)->where('is_active', true)->exists();

        $status = $active ? Password::sendResetLink(['email' => $email]) : Password::INVALID_USER;

        if ($status === Password::RESET_THROTTLED) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'طلبت رابطاً قبل قليل. انتظر دقيقة ثم حاول مرة أخرى.']);
        }

        // نفس الرسالة سواء كان البريد مسجلاً أم لا، حتى لا تُكشف حسابات المستخدمين
        return back()->with('status', 'إذا كان هذا البريد مسجلاً لدينا، ستصلك خلال دقائق رسالة فيها رابط تعيين كلمة مرور جديدة.');
    }
}
