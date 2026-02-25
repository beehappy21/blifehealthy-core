<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\MerchantController;

Route::get('/ping', fn () => response()->json(['ok' => true]));

Route::get('/ref/validate', [ReferralController::class, 'validateCode']);

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Merchant
    Route::post('/merchant/apply', [MerchantController::class, 'apply']);
    Route::post('/merchant/kyc', [MerchantController::class, 'submitKyc']);
    Route::post('/merchant/location', [MerchantController::class, 'addLocation']);

    // Admin
    Route::middleware('admin')->group(function () {
        Route::post('/admin/merchant/review', [MerchantController::class, 'adminReview']);
    });
    Route::get('/merchant/me', [MerchantController::class, 'myMerchant']);

Route::middleware('admin')->group(function () {
    Route::get('/admin/merchant/{merchantId}', [MerchantController::class, 'adminMerchantDetail']);
});
});