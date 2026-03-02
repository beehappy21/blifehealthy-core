<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function myNotifications(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $limit = max(1, min(100, (int) $request->query('limit', 50)));
        $before = (string) $request->query('before', '');

        $orderItems = $this->fetchOrderNotifications((int) $user->id, $limit * 2, $before);
        $couponItems = $this->fetchCouponNotifications((int) $user->id, $limit * 2, $before);

        $items = array_merge($orderItems, $couponItems);
        usort($items, fn ($a, $b) => strcmp((string) $b['created_at'], (string) $a['created_at']));
        $items = array_slice($items, 0, $limit);

        $nextBefore = null;
        if (count($items) === $limit) {
            $nextBefore = $items[array_key_last($items)]['created_at'] ?? null;
        }

        return response()->json([
            'ok' => true,
            'items' => $items,
            'pagination' => [
                'limit' => $limit,
                'next_before' => $nextBefore,
            ],
        ]);
    }

    private function fetchOrderNotifications(int $userId, int $limit, string $before): array
    {
        if (!DB::getSchemaBuilder()->hasTable('integration_outbox')) {
            return [];
        }

        $q = DB::table('integration_outbox')
            ->whereIn('event_type', [
                'order.created',
                'slip.submitted',
                'slip.approved',
                'slip.rejected',
                'order.shipped',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit);

        if ($before !== '') {
            $q->where('created_at', '<', $before);
        }

        $rows = $q->get();
        $items = [];

        foreach ($rows as $row) {
            $payload = json_decode((string) $row->payload_json, true) ?: [];
            $ownerUserId = (int) ($payload['user_id'] ?? 0);

            if ($ownerUserId === 0 && isset($payload['order_id'])) {
                $ownerUserId = (int) DB::table('orders')->where('id', (int) $payload['order_id'])->value('user_id');
            }

            if ($ownerUserId !== $userId) {
                continue;
            }

            $items[] = [
                'id' => 'order-' . $row->id,
                'source' => 'order',
                'type' => $row->event_type,
                'title' => $this->orderTitle((string) $row->event_type),
                'message' => $this->orderMessage((string) $row->event_type, $payload),
                'payload' => $payload,
                'created_at' => (string) $row->created_at,
            ];
        }

        return $items;
    }

    private function fetchCouponNotifications(int $userId, int $limit, string $before): array
    {
        if (!DB::getSchemaBuilder()->hasTable('coupon_events')) {
            return [];
        }

        $q = DB::table('coupon_events')
            ->join('coupons', 'coupons.id', '=', 'coupon_events.coupon_id')
            ->where('coupons.buyer_user_id', $userId)
            ->orderByDesc('coupon_events.created_at')
            ->orderByDesc('coupon_events.id')
            ->select([
                'coupon_events.id',
                'coupon_events.type',
                'coupon_events.payload',
                'coupon_events.created_at',
                'coupons.code as coupon_code',
                'coupons.status as coupon_status',
            ])
            ->limit($limit);

        if ($before !== '') {
            $q->where('coupon_events.created_at', '<', $before);
        }

        $rows = $q->get();
        $items = [];

        foreach ($rows as $row) {
            $payload = is_string($row->payload) ? (json_decode($row->payload, true) ?: []) : ((array) $row->payload);
            $payload['coupon_code'] = $row->coupon_code;
            $payload['coupon_status'] = $row->coupon_status;

            $items[] = [
                'id' => 'coupon-' . $row->id,
                'source' => 'coupon',
                'type' => (string) $row->type,
                'title' => 'อีเวนต์คูปอง',
                'message' => 'คูปอง ' . ($row->coupon_code ?? '-') . ' สถานะ ' . ($row->coupon_status ?? '-'),
                'payload' => $payload,
                'created_at' => (string) $row->created_at,
            ];
        }

        return $items;
    }

    private function orderTitle(string $type): string
    {
        return match ($type) {
            'order.created' => 'สร้างคำสั่งซื้อ',
            'slip.submitted' => 'อัปโหลดสลิป',
            'slip.approved' => 'สลิปผ่านการตรวจสอบ',
            'slip.rejected' => 'สลิปไม่ผ่านการตรวจสอบ',
            'order.shipped' => 'จัดส่งคำสั่งซื้อแล้ว',
            default => 'อีเวนต์คำสั่งซื้อ',
        };
    }

    private function orderMessage(string $type, array $payload): string
    {
        $orderNo = $payload['order_no'] ?? ('#' . ($payload['order_id'] ?? '-'));

        return match ($type) {
            'order.created' => "สร้างคำสั่งซื้อ {$orderNo} สำเร็จ",
            'slip.submitted' => "อัปโหลดสลิปสำหรับ {$orderNo} แล้ว",
            'slip.approved' => "สลิปของ {$orderNo} ผ่านการตรวจสอบ",
            'slip.rejected' => "สลิปของ {$orderNo} ไม่ผ่านการตรวจสอบ",
            'order.shipped' => "คำสั่งซื้อ {$orderNo} ถูกจัดส่งแล้ว",
            default => 'มีความเคลื่อนไหวคำสั่งซื้อใหม่',
        };
    }
}
