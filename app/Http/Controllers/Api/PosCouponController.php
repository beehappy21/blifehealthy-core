<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\PosDevice;

class PosCouponController extends Controller
{
    private function myApprovedMerchantOrFail($actor)
    {
        // POS device token
        if ($actor instanceof PosDevice) {
            $m = DB::table('merchants')->where('id', (int)$actor->merchant_id)->first();
            if (!$m) return [null, response()->json(['ok' => false, 'error' => 'MERCHANT_NOT_FOUND', 'message' => 'merchant not found'], 404)];
            if ($m->status !== 'approved') return [null, response()->json(['ok' => false, 'error' => 'MERCHANT_NOT_APPROVED', 'message' => 'merchant not approved'], 403)];
            return [$m, null];
        }

        // owner user token
        $m = DB::table('merchants')->where('owner_user_id', (int)$actor->id)->first();
        if (!$m) return [null, response()->json(['ok' => false, 'error' => 'MERCHANT_NOT_FOUND', 'message' => 'merchant not found'], 404)];
        if ($m->status !== 'approved') return [null, response()->json(['ok' => false, 'error' => 'MERCHANT_NOT_APPROVED', 'message' => 'merchant not approved'], 403)];
        return [$m, null];
    }

    private function redeemedByUserId($actor, $merchant): ?int
    {
        // coupons.redeemed_by_user_id เป็น FK ไป users → ห้ามใส่ pos_device id
        if ($actor instanceof PosDevice) {
            return $actor->created_by_user_id ? (int)$actor->created_by_user_id : (int)$merchant->owner_user_id;
        }
        return (int)$actor->id;
    }

    private function couponDetailByCode(string $code)
    {
        return DB::table('coupons')
            ->join('coupon_products', 'coupon_products.id', '=', 'coupons.coupon_product_id')
            ->join('products', 'products.id', '=', 'coupons.product_id')
            ->join('users as buyer', 'buyer.id', '=', 'coupons.buyer_user_id')
            ->where('coupons.code', $code)
            ->select([
                'coupons.*',
                'coupon_products.discount_type',
                'coupon_products.discount_value',
                'coupon_products.expiry_days',
                'coupon_products.require_confirm',
                'coupon_products.terms',
                'products.name as product_name',
                'buyer.name as buyer_name',
                'buyer.phone as buyer_phone',
            ])
            ->first();
    }

    private function logEventIfExists(int $couponId, string $type, array $payload, $now)
    {
        if (!DB::getSchemaBuilder()->hasTable('coupon_events')) return;

        DB::table('coupon_events')->insert([
            'coupon_id' => $couponId,
            'type' => $type,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    // GET /api/pos/coupons/{code}
    public function lookup(Request $request, $code)
    {
        $actor = $request->user();
        [$merchant, $err] = $this->myApprovedMerchantOrFail($actor);
        if ($err) return $err;

        $c = $this->couponDetailByCode($code);
        if (!$c) return response()->json(['ok' => false, 'error' => 'COUPON_NOT_FOUND', 'message' => 'coupon not found'], 404);
        if ((int)$c->merchant_id !== (int)$merchant->id) return response()->json(['ok' => false, 'error' => 'FORBIDDEN', 'message' => 'forbidden'], 403);

        return response()->json(['ok' => true, 'coupon' => $c]);
    }

    // POST /api/pos/coupons/{code}/scan
    public function scan(Request $request, $code)
    {
        $actor = $request->user();
        [$merchant, $err] = $this->myApprovedMerchantOrFail($actor);
        if ($err) return $err;

        $c = $this->couponDetailByCode($code);
        if (!$c) return response()->json(['ok' => false, 'error' => 'COUPON_NOT_FOUND', 'message' => 'coupon not found'], 404);
        if ((int)$c->merchant_id !== (int)$merchant->id) return response()->json(['ok' => false, 'error' => 'FORBIDDEN', 'message' => 'forbidden'], 403);

        if ($c->expires_at && now()->gt($c->expires_at)) {
            return response()->json(['ok' => false, 'error' => 'COUPON_EXPIRED', 'message' => 'coupon expired'], 422);
        }

        // B) redeemed/void => 409
        if (in_array($c->status, ['redeemed', 'void'], true)) {
            return response()->json([
                'ok' => false,
                'error' => 'COUPON_NOT_USABLE',
                'status' => $c->status,
                'message' => 'coupon not usable'
            ], 409);
        }

        $now = now();
        $redeemedByUserId = $this->redeemedByUserId($actor, $merchant);

        $payloadBase = [
            'merchant_id' => (int)$merchant->id,
            'redeemed_by_user_id' => $redeemedByUserId,
        ];
        if ($actor instanceof PosDevice) {
            $payloadBase['pos_device_id'] = (int)$actor->id;
        }

        $this->logEventIfExists((int)$c->id, 'scanned', $payloadBase, $now);

        if ((int)$c->require_confirm === 1) {

            if ($c->status === 'confirmed') {
                return response()->json([
                    'ok' => true,
                    'status' => 'confirmed',
                    'need_confirm' => false
                ]);
            }

            DB::table('coupons')->where('id', (int)$c->id)->update([
                'status' => 'pending_confirm',
                'redeemed_by_user_id' => $redeemedByUserId,
                'updated_at' => $now,
            ]);

            $this->logEventIfExists((int)$c->id, 'scan_requested_confirm', $payloadBase, $now);

            return response()->json([
                'ok' => true,
                'status' => 'pending_confirm',
                'need_confirm' => true
            ]);
        }

        DB::table('coupons')->where('id', (int)$c->id)->update([
            'status' => 'redeemed',
            'redeemed_at' => $now,
            'redeemed_by_user_id' => $redeemedByUserId,
            'updated_at' => $now,
        ]);

        $this->logEventIfExists((int)$c->id, 'redeemed', $payloadBase, $now);

        return response()->json([
            'ok' => true,
            'status' => 'redeemed',
            'need_confirm' => false
        ]);
    }

    // POST /api/pos/coupons/{code}/redeem
    public function redeem(Request $request, $code)
    {
        $actor = $request->user();
        [$merchant, $err] = $this->myApprovedMerchantOrFail($actor);
        if ($err) return $err;

        $c = $this->couponDetailByCode($code);
        if (!$c) return response()->json(['ok' => false, 'error' => 'COUPON_NOT_FOUND', 'message' => 'coupon not found'], 404);
        if ((int)$c->merchant_id !== (int)$merchant->id) return response()->json(['ok' => false, 'error' => 'FORBIDDEN', 'message' => 'forbidden'], 403);

        if ($c->expires_at && now()->gt($c->expires_at)) {
            return response()->json(['ok' => false, 'error' => 'COUPON_EXPIRED', 'message' => 'coupon expired'], 422);
        }

        // idempotent: redeemed => 200
        if ($c->status === 'redeemed') {
            return response()->json(['ok' => true, 'status' => 'redeemed']);
        }

        if ((int)$c->require_confirm === 1 && $c->status !== 'confirmed') {
            return response()->json(['ok' => false, 'error' => 'NEED_BUYER_CONFIRM', 'message' => 'need buyer confirmation'], 422);
        }

        $now = now();
        $redeemedByUserId = $this->redeemedByUserId($actor, $merchant);

        DB::table('coupons')->where('id', (int)$c->id)->update([
            'status' => 'redeemed',
            'redeemed_at' => $now,
            'redeemed_by_user_id' => $redeemedByUserId,
            'updated_at' => $now,
        ]);

        $payload = [
            'merchant_id' => (int)$merchant->id,
            'redeemed_by_user_id' => $redeemedByUserId,
        ];
        if ($actor instanceof PosDevice) {
            $payload['pos_device_id'] = (int)$actor->id;
        }

        $this->logEventIfExists((int)$c->id, 'redeemed', $payload, $now);

        return response()->json(['ok' => true, 'status' => 'redeemed']);
    }
}