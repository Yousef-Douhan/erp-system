<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Database\QueryException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // محاولة حذف سجل مرتبط بسجلات أخرى (مثلاً منتج له مبيعات) => رسالة واضحة بدل صفحة خطأ
        $this->renderable(function (QueryException $e, $request) {
            $isForeignKeyError = ($e->errorInfo[1] ?? null) === 1451;

            if ($isForeignKeyError) {
                $message = 'لا يمكن حذف هذا السجل لأنه مرتبط بسجلات أخرى (فواتير أو حركات). يمكنك إيقافه بدلاً من حذفه.';

                return $request->expectsJson()
                    ? response()->json(['success' => false, 'message' => $message], 409)
                    : back()->with('error', $message);
            }

            return null;
        });

        // رفض من Policy (authorize): رسالة عربية واضحة بدل صفحة 403
        $this->renderable(function (AccessDeniedHttpException $e, $request) {
            $message = 'ليس لديك صلاحية لتنفيذ هذه العملية.';

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $message], 403);
            }

            $back = url()->previous();
            return redirect()->to($back && $back !== $request->fullUrl() ? $back : route('dashboard'))->with('error', $message);
        });

        // صفحة واضحة عند عدم امتلاك الصلاحية
        $this->renderable(function (UnauthorizedException $e, $request) {
            $message = 'ليس لديك صلاحية للوصول إلى هذه الصفحة.';

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $message], 403)
                : redirect()->route('dashboard')->with('error', $message);
        });
    }
}
