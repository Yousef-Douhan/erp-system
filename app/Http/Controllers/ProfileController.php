<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * عرض صفحة الملف الشخصي
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * تحديث بيانات الملف الشخصي
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * حذف حساب المستخدم الحالي
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // مدير النظام وصاحب الصيدلية لا يوقفان حسابهما بأنفسهما حتى لا تبقى المنشأة بلا مسؤول
        if (self::cannotSelfDelete($user)) {
            return Redirect::route('profile.edit')->withErrors(
                ['password' => 'لا يمكن إيقاف حساب مدير النظام أو صاحب الصيدلية من هنا.'],
                'userDeletion'
            );
        }

        Auth::logout();

        // حذف ناعم: الحساب يُوقف ويُنقل لسلة المحذوفات ولا تُمسح أي بيانات
        $user->forceFill(['is_active' => false])->save();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    public static function cannotSelfDelete($user): bool
    {
        return $user->hasRole('Admin')
            || \App\Support\Tenancy::isSuperAdmin($user)
            || $user->hasRole(\App\Support\PermissionCatalog::OWNER_ROLE);
    }
}
