<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CouponProductController extends Controller
{
    private function myApprovedMerchantOrFail($user)
    {
        $m = DB::table('merchants')->where('owner_user_id', $user->id)->first();
        if (!$m) return [null, response()->json(['ok' => false, 'message' => 'merchant not found'], 404)];
        if ($m->status !== 'approved') return [null, response()->json(['ok' => false, 'message' => 'merchant not approved'], 403)];
        return [$m, null];
    }

    // GET /api/merchant/products/{id}/coupon-product
    public function get(Request $request, $id)
    {
        $user = $request->user();
        [$merchant, $err] = $this->myApprovedMerchantOrFail($user);
        if ($err) return $err;

        $product = DB::table('products')->where('id', (int)$id)->first();
        if (!$product) return response()->json(['ok' => false, 'message' => 'product not found'], 404);
        if ((int)$product->merchant_id !== (int)$merchant->id) return response()->json(['ok' => false, 'message' => 'forbidden'], 403);

        $cfg = DB::table('coupon_products')->where('product_id', (int)$id)->first();

        return response()->json(['ok' => true, 'coupon_product' => $cfg]);
    }

    // POST /api/merchant/products/{id}/coupon-product
    // 1 product ต่อ 1 coupon_products (unique)
    public function upsert(Request $request, $id)
    {
        $user = $request->user();
        [$merchant, $err] = $this->myApprovedMerchantOrFail($user);
        if ($err) return $err;

        $product = DB::table('products')->where('id', (int)$id)->first();
        if (!$product) return response()->json(['ok' => false, 'message' => 'product not found'], 404);
        if ((int)$product->merchant_id !== (int)$merchant->id) return response()->json(['ok' => false, 'message' => 'forbidden'], 403);

        $data = $request->validate([
            'discount_type'   => ['nullable', 'in:percent,fixed'],
            'discount_value'  => ['nullable', 'numeric', 'min:0'],
            'expiry_days'     => ['nullable', 'integer', 'min:1', 'max:3650'],
            'require_confirm' => ['nullable', 'boolean'], // 1=ต้องยืนยันก่อน redeem
            'terms'           => ['nullable', 'string'],
        ]);

        $now = now();

        DB::transaction(function () use ($id, $merchant, $data, $now) {
            $exists = DB::table('coupon_products')->where('product_id', (int)$id)->first();

            $payload = [
                'product_id'       => (int)$id,
                'merchant_id'      => (int)$merchant->id,
                'discount_type'    => $data['discount_type'] ?? ($exists->discount_type ?? 'fixed'),
                'discount_value'   => array_key_exists('discount_value', $data) ? $data['discount_value'] : ($exists->discount_value ?? 0),
                'expiry_days'      => $data['expiry_days'] ?? ($exists->expiry_days ?? 30),
                'require_confirm'  => array_key_exists('require_confirm', $data) ? (int)$data['require_confirm'] : (int)($exists->require_confirm ?? 1),
                'terms'            => array_key_exists('terms', $data) ? $data['terms'] : ($exists->terms ?? null),
                'updated_at'       => $now,
            ];

            if (!$exists) {
                $payload['created_at'] = $now;
                DB::table('coupon_products')->insert($payload);
            } else {
                DB::table('coupon_products')->where('id', (int)$exists->id)->update($payload);
            }
        });

        $cfg = DB::table('coupon_products')->where('product_id', (int)$id)->first();
        return response()->json(['ok' => true, 'coupon_product' => $cfg]);
    }
}