<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\SupplierController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| هنا يتم تسجيل مسارات الـ API الخاصة بنظام الـ ERP والصيدلية.
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// مسارات العمليات الرئيسية للنظام (ERP & Pharmacy)
Route::prefix('v1')->group(function () {

    // مسارات المبيعات (Sale Transaction & FIFO & Prescriptions)
    Route::post('/sales', [SaleController::class, 'store']);

    // مسارات المشتريات (Purchases & Batches Creation)
    Route::post('/purchases', [PurchaseController::class, 'store']);

    // مسارات المنتجات والعملاء والموردين (Resource Routes الأساسية)
    Route::apiResource('products', ProductController::class);
    Route::apiResource('customers', CustomerController::class);
    Route::apiResource('suppliers', SupplierController::class);
});