<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CouponController extends Controller
{
    private function genCode(): string
    {
        return 'CP' . now()->format('ymd') . '-' . Str::upper(Str::random(6));
    }

    // POST /api/products/{id}/coupons/issue
    public function issue(Request $request, $id)
    {
        $user = $request->user();

        $cfg = DB::table('coupon_products')->where('product_id', (int)$id)->first();
        if (!$cfg) return response()->json(['ok' => false, 'message' => 'coupon config not found'], 404);

        $product = DB::table('products')->where('id', (int)$id)->first();
        if (!$product) return response()->json(['ok' => false, 'message' => 'product not found'], 404);

        $merchant = DB::table('merchants')->where('id', (int)$cfg->merchant_id)->first();
        if (!$merchant || $merchant->status !== 'approved') {
            return response()->json(['ok' => false, 'message' => 'merchant not approved'], 403);
        }

        $coupon = DB::transaction(function () use ($cfg, $product, $merchant, $user) {
            $code = null;
            for ($i = 0; $i < 10; $i++) {
                $try = $this->genCode();
                if (!DB::table('coupons')->where('code', $try)->exists()) { $code = $try; break; }
            }
            if (!$code) abort(500, 'cannot generate coupon code');

            $now = now();
            $expiresAt = $now->copy()->addDays((int)$cfg->expiry_days);

            $couponId = DB::table('coupons')->insertGetId([
                'code' => $code,
                'coupon_product_id' => (int)$cfg->id,
                'product_id' => (int)$product->id,
                'merchant_id' => (int)$merchant->id,
                'buyer_user_id' => (int)$user->id,
                'status' => 'issued',
                'issued_at' => $now,
                'expires_at' => $expiresAt,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return DB::table('coupons')->where('id', (int)$couponId)->first();
        });

        return response()->json([
            'ok' => true,
            'coupon' => $coupon,
            'qr_text' => $coupon->code,
        ]);
    }

    // GET /api/me/coupons?status=
    public function myCoupons(Request $request)
    {
        $user = $request->user();
        $status = $request->query('status');

        $q = DB::table('coupons')
            ->join('products', 'products.id', '=', 'coupons.product_id')
            ->join('merchants', 'merchants.id', '=', 'coupons.merchant_id')
            ->where('coupons.buyer_user_id', (int)$user->id)
            ->orderByDesc('coupons.id')
            ->select([
                'coupons.id',
                'coupons.code',
                'coupons.status',
                'coupons.issued_at',
                'coupons.expires_at',
                'coupons.confirmed_at',
                'coupons.redeemed_at',
                'coupons.product_id',
                'products.name as product_name',
                'coupons.merchant_id',
                'merchants.shop_name as merchant_name',
            ]);

        if ($status) $q->where('coupons.status', $status);

        return response()->json(['ok' => true, 'items' => $q->get()]);
    }

    // POST /api/me/coupons/{code}/confirm
    public function confirm(Request $request, $code)
    {
        $user = $request->user();

        $coupon = DB::table('coupons')->where('code', $code)->first();
        if (!$coupon) return response()->json(['ok' => false, 'message' => 'coupon not found'], 404);
        if ((int)$coupon->buyer_user_id !== (int)$user->id) {
            return response()->json(['ok' => false, 'message' => 'forbidden'], 403);
        }

        if ($coupon->expires_at && now()->gt($coupon->expires_at)) {
            return response()->json(['ok' => false, 'message' => 'coupon expired'], 422);
        }

        // ยืนยันได้เฉพาะ pending_confirm
        if ($coupon->status !== 'pending_confirm') {
            return response()->json(['ok' => false, 'message' => 'invalid status'], 422);
        }

        DB::table('coupons')->where('id', (int)$coupon->id)->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'updated_at' => now(),
        ]);

        // ✅ log event (confirmed)
        if (DB::getSchemaBuilder()->hasTable('coupon_events')) {
            DB::table('coupon_events')->insert([
                'coupon_id' => (int)$coupon->id,
                'type' => 'confirmed',
                'payload' => json_encode(['buyer_user_id' => (int)$user->id], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json(['ok' => true, 'status' => 'confirmed']);
    }
}