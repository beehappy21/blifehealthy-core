<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\MerchantController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReviewController;

Route::get('/ping', fn () => response()->json(['ok' => true]));

// referral
Route::get('/ref/validate', [ReferralController::class, 'validateCode']);

// auth
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

/**
 * PUBLIC (ลูกค้า)
 */
Route::get('/shop/{merchantId}/products', [ProductController::class, 'listShop']);
Route::get('/products/{id}', [ProductController::class, 'detail']);

// reviews public (ดูรีวิวได้)
Route::get('/products/{id}/reviews', [ReviewController::class, 'list']);

/**
 * PROTECTED (ต้องมี token)
 */
Route::middleware('auth:sanctum')->group(function () {

    // me
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // merchant profile / onboarding
    Route::post('/merchant/apply', [MerchantController::class, 'apply']);
    Route::post('/merchant/kyc', [MerchantController::class, 'submitKyc']);
    Route::post('/merchant/location', [MerchantController::class, 'addLocation']);
    Route::get('/merchant/me', [MerchantController::class, 'myMerchant']);

    // merchant products
    Route::post('/merchant/products', [ProductController::class, 'create']);
    Route::patch('/merchant/products/{id}', [ProductController::class, 'update']);
    Route::get('/merchant/products', [ProductController::class, 'listMine']);

    // variants
    Route::post('/merchant/products/{id}/variants', [ProductController::class, 'addVariant']);
    Route::patch('/merchant/variants/{id}/stock', [ProductController::class, 'updateStock']);

    // ✅ product images (merchant) — ไม่ต้องเป็น admin
    Route::post('/merchant/products/{id}/images', [ProductController::class, 'addImage']);
    Route::patch('/merchant/images/{imageId}', [ProductController::class, 'updateImage']);
    Route::delete('/merchant/images/{imageId}', [ProductController::class, 'deleteImage']);

    // reviews ต้องมี token (เขียน/ตอบรีวิว)
    Route::post('/products/{id}/reviews', [ReviewController::class, 'createOrUpdate']);
    Route::post('/merchant/reviews/{id}/reply', [ReviewController::class, 'reply']);

    // admin only
    Route::middleware('admin')->group(function () {
        Route::post('/admin/merchant/review', [MerchantController::class, 'adminReview']);
        Route::get('/admin/merchant/{merchantId}', [MerchantController::class, 'adminMerchantDetail']);
    });

});