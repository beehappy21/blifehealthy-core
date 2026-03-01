<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\MerchantController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReviewController;

use App\Http\Controllers\Api\CouponProductController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\PosCouponController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PosDeviceController;
use App\Http\Controllers\Api\MerchantPosDeviceController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\MerchantPaymentSlipController;

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

    // ✅ notifications (ต้องมี token)
    Route::get('/me/notifications', [NotificationController::class, 'myNotifications']);

    // --- ลูกค้า (My Coupons)
    Route::get('/me/coupons', [CouponController::class, 'myCoupons']);
    Route::post('/me/coupons/{code}/confirm', [CouponController::class, 'confirm']);

    // --- ออกคูปอง (buyer)
    Route::post('/products/{id}/coupons/issue', [CouponController::class, 'issue']);

    // --- ร้านค้า ตั้งค่า “สินค้าเป็นคูปอง”
    Route::post('/merchant/products/{id}/coupon-product', [CouponProductController::class, 'upsert']);
    Route::get('/merchant/products/{id}/coupon-product', [CouponProductController::class, 'get']);

    // --- POS Devices (merchant) create/list/rotate/revoke
    Route::get('/merchant/pos-devices', [MerchantPosDeviceController::class, 'index']);
    Route::post('/merchant/pos-devices', [MerchantPosDeviceController::class, 'store']);
    Route::post('/merchant/pos-devices/{id}/rotate-token', [MerchantPosDeviceController::class, 'rotateToken']);
    Route::post('/merchant/pos-devices/{id}/revoke', [MerchantPosDeviceController::class, 'revoke']);

    // --- POS (ร้านค้า) scan / redeem
    Route::get('/pos/me', [PosDeviceController::class, 'me']);
    Route::get('/pos/coupons/{code}', [PosCouponController::class, 'lookup'])
        ->middleware(['pos.device:pos:lookup']);
    Route::post('/pos/coupons/{code}/scan', [PosCouponController::class, 'scan'])
        ->middleware(['pos.device:pos:scan']);
    Route::post('/pos/coupons/{code}/redeem', [PosCouponController::class, 'redeem'])
        ->middleware(['pos.device:pos:redeem']);


    // --- eCommerce customer addresses
    Route::get('/me/addresses', [AddressController::class, 'index']);
    Route::post('/me/addresses', [AddressController::class, 'store']);
    Route::patch('/me/addresses/{id}', [AddressController::class, 'update']);
    Route::delete('/me/addresses/{id}', [AddressController::class, 'destroy']);

    // --- eCommerce orders
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::post('/orders/{id}/payment-slip', [OrderController::class, 'uploadSlip']);

    // --- merchant payment slips review
    Route::get('/merchant/payment-slips', [MerchantPaymentSlipController::class, 'index']);
    Route::post('/merchant/payment-slips/{id}/approve', [MerchantPaymentSlipController::class, 'approve']);
    Route::post('/merchant/payment-slips/{id}/reject', [MerchantPaymentSlipController::class, 'reject']);
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

    // product images (merchant)
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