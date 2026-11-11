<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * إذا أوقف المدير حساب مستخدم وهو مسجل الدخول، يخرج من النظام في الطلب التالي مباشرة.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // الحساب موقوف، أو صيدليته موقوفة من مدير النظام، أو فرعه موقوف
        $message = null;
        if ($user && ! $user->is_active) {
            $message = 'تم إيقاف حسابك. تواصل مع مدير النظام.';
        } elseif ($user && $user->organization_id && ! \App\Support\Tenancy::isSuperAdmin($user)) {
            $orgStatus = self::statusOf(\App\Models\Organization::class, $user->organization_id);
            $branchStatus = $user->branch_id ? self::statusOf(\App\Models\Branch::class, $user->branch_id) : 'active';
            if ($orgStatus === 'inactive') {
                $message = 'حساب الصيدلية موقوف حالياً. تواصل مع إدارة النظام.';
            } elseif ($branchStatus === 'inactive') {
                $message = 'هذا الفرع موقوف حالياً. تواصل مع صاحب الصيدلية.';
            }
        }

        if ($message) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 401);
            }

            return redirect()->route('login')->withErrors(['email' => $message]);
        }

        return $next($request);
    }

    /** حالة الصيدلية/الفرع، والمحذوف (حذفاً ناعماً) يُعامل كموقوف */
    private static function statusOf(string $model, int $id): string
    {
        $row = $model::withoutGlobalScopes()->whereKey($id)->first(['status', 'deleted_at']);

        return (!$row || $row->deleted_at) ? 'inactive' : (string) $row->status;
    }
}
