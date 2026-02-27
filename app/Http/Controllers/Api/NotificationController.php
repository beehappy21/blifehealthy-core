<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    // GET /api/me/notifications?type=scan_requested_confirm
    public function myNotifications(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $type = $request->query('type');

        // ถ้ายังไม่มีตาราง (กัน dev เผลอยังไม่ migrate)
        if (!DB::getSchemaBuilder()->hasTable('coupon_events')) {
            return response()->json(['ok' => true, 'items' => []]);
        }

        $q = DB::table('coupon_events')
            ->join('coupons', 'coupons.id', '=', 'coupon_events.coupon_id')
            ->join('products', 'products.id', '=', 'coupons.product_id')
            ->join('merchants', 'merchants.id', '=', 'coupons.merchant_id')
            ->where('coupons.buyer_user_id', (int)$user->id)
            ->orderByDesc('coupon_events.id')
            ->select([
                'coupon_events.id',
                'coupon_events.type',
                'coupon_events.payload',
                'coupon_events.created_at',
                'coupons.code as coupon_code',
                'coupons.status as coupon_status',
                'products.name as product_name',
                'merchants.shop_name as merchant_name',
            ]);

        if ($type) $q->where('coupon_events.type', $type);

        return response()->json(['ok' => true, 'items' => $q->limit(100)->get()]);
    }
}