<?php

use App\Http\Controllers\Api\V1\PurchaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| كل مسارات الـ API محمية بـ Sanctum (لازم Token) وبالصلاحيات.
| ملاحظة: المسارات القديمة للمنتجات والعملاء والموردين والمبيعات كانت تستدعي
| Controllers الويب (التي ترجع صفحات HTML وليس JSON) وبدون أي حماية، لذلك تم إيقافها.
|
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::prefix('v1')->group(function () {
        // المشتريات (إنشاء فاتورة + دفعات + زيادة المخزون)
        Route::post('/purchases', [PurchaseController::class, 'store'])
            ->middleware('permission:create_purchases');
    });
});
