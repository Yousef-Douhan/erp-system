<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| لا يوجد API عام حالياً. مسار المشتريات القديم (/api/v1/purchases) أُزيل لأنه كان يتجاوز
| حساب المورد ورصيد الفرع والقيود المحاسبية. أي API جديد يجب أن يستدعي نفس الخدمات
| التي تستخدمها الشاشات (SupplierLedger، StockService، CashBook...).
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user()->only(['id', 'username', 'email', 'organization_id', 'branch_id']);
});
