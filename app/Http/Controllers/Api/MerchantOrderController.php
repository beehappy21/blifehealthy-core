<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Support\ApiError;
use App\Support\OrderEventLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MerchantOrderController extends Controller
{
    public function index(Request $request)
    {
        $ownerId = (int) $request->user()->id;
        $status = OrderStatus::normalize((string) $request->query('status', ''));
        $q = trim((string) $request->query('q', ''));

        $query = DB::table('orders')
            ->join('merchants', 'merchants.id', '=', 'orders.merchant_id')
            ->leftJoin('payment_slips', 'payment_slips.order_id', '=', 'orders.id')
            ->leftJoin('shipments', 'shipments.order_id', '=', 'orders.id')
            ->where('merchants.owner_user_id', $ownerId)
            ->select([
                'orders.id',
                'orders.order_no',
                'orders.status',
                'orders.total',
                'orders.paid_at',
                'orders.created_at',
                'payment_slips.id as payment_slip_id',
                'payment_slips.status as payment_slip_status',
                'payment_slips.image_url as payment_slip_image_url',
                'payment_slips.amount as payment_slip_amount',
                'shipments.provider as shipment_provider',
                'shipments.tracking_no as shipment_tracking_no',
                'shipments.fee as shipment_fee',
            ])
            ->orderByDesc('orders.id')
            ->limit(200);

        if ($status !== '' && in_array($status, OrderStatus::values(), true)) {
            $query->where('orders.status', $status);
        }

        if ($q !== '') {
            $query->where(function ($subQuery) use ($q) {
                $subQuery->where('orders.order_no', 'like', '%' . $q . '%')
                    ->orWhere('shipments.tracking_no', 'like', '%' . $q . '%')
                    ->orWhere('payment_slips.status', 'like', '%' . strtolower($q) . '%');
            });
        }

        return response()->json(['ok' => true, 'items' => $query->get()]);
    }

    public function show(Request $request, $id)
    {
        $ownerId = (int) $request->user()->id;

        $order = DB::table('orders')
            ->join('merchants', 'merchants.id', '=', 'orders.merchant_id')
            ->where('orders.id', (int) $id)
            ->where('merchants.owner_user_id', $ownerId)
            ->select('orders.*')
            ->first();

        if (!$order) {
            if (DB::table('orders')->where('id', (int) $id)->exists()) {
                return ApiError::forbidden('ไม่มีสิทธิ์');
            }
            return response()->json(['ok' => false, 'message' => 'order not found'], 404);
        }

        $items = DB::table('order_items')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->where('order_items.order_id', (int) $id)
            ->orderBy('order_items.id')
            ->select(['order_items.*', 'products.name as product_name'])
            ->get();

        return response()->json([
            'ok' => true,
            'order' => $order,
            'items' => $items,
            'payment_slip' => DB::table('payment_slips')->where('order_id', (int) $id)->first(),
            'shipment' => DB::table('shipments')->where('order_id', (int) $id)->first(),
            'address_snapshot' => null,
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $ownerId = (int) $request->user()->id;
        $data = $request->validate(['status' => ['required', 'string']]);
<<<<<<< HEAD
        $status = OrderStatus::normalize($data['status']);
        Validator::make(['status' => $status], ['status' => ['required', 'in:' . implode(',', OrderStatus::values())]])->validate();
=======
        $data['status'] = OrderStatus::normalize($data['status']);

        Validator::make($data, ['status' => ['required', 'in:' . implode(',', OrderStatus::values())]])->validate();
>>>>>>> origin/main

        if ($status === OrderStatus::SHIPPED) {
            return $this->markShipped($request, $id);
        }

<<<<<<< HEAD
        if ($status === OrderStatus::CANCELLED) {
            return $this->cancelOrder($request, $id);
        }

        $order = $this->getOwnedOrderOrError((int) $id, $ownerId);
        if ($order instanceof \Illuminate\Http\JsonResponse) {
            return $order;
        }

        DB::table('orders')->where('id', (int) $id)->update(['status' => $status, 'updated_at' => now()]);

=======
>>>>>>> origin/main
        return response()->json(['ok' => true, 'order' => DB::table('orders')->where('id', (int) $id)->first()]);
    }

    public function upsertShipment(Request $request, $id)
    {
        $ownerId = (int) $request->user()->id;
        $data = $request->validate([
            'provider' => ['required', 'string', 'max:50'],
            'tracking_no' => ['required', 'string', 'max:100'],
            'fee' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', 'max:30'],
        ]);

        $order = $this->getOwnedOrderOrError((int) $id, $ownerId);
        if ($order instanceof \Illuminate\Http\JsonResponse) {
            return $order;
        }

        if (!in_array($order->status, [OrderStatus::PAID, OrderStatus::SHIPPING_CREATED], true)) {
            return ApiError::orderStateConflict('สถานะออเดอร์ไม่ถูกต้อง กรุณารีเฟรช', ['order_status' => $order->status]);
        }

        $shipment = DB::transaction(function () use ($id, $data) {
            $order = DB::table('orders')->where('id', (int) $id)->lockForUpdate()->first();
            if (!$order) {
                return null;
            }

            $shipmentPayload = [
                'provider' => $data['provider'],
                'tracking_no' => $data['tracking_no'],
                'fee' => round((float) ($data['fee'] ?? 0), 2),
                'status' => $data['status'] ?? 'created',
                'updated_at' => now(),
            ];

            Shipment::updateOrCreate(
                ['order_id' => (int) $id],
                $shipmentPayload
            );

            DB::table('orders')->where('id', (int) $id)->update([
                'status' => OrderStatus::SHIPPING_CREATED,
                'updated_at' => now(),
            ]);

            return DB::table('shipments')->where('order_id', (int) $id)->first();
        });

        if (!$shipment) {
            return response()->json(['ok' => false, 'message' => 'order not found'], 404);
        }

        return response()->json(['ok' => true, 'shipment' => $shipment, 'order' => DB::table('orders')->where('id', (int) $id)->first()]);
    }

    public function markShipped(Request $request, $id)
    {
        $ownerId = (int) $request->user()->id;
        $order = $this->getOwnedOrderOrError((int) $id, $ownerId);
        if ($order instanceof \Illuminate\Http\JsonResponse) {
            return $order;
        }

        $updated = DB::transaction(function () use ($id, $order) {
            $locked = DB::table('orders')->where('id', (int) $id)->lockForUpdate()->first();
            if (!$locked) {
                return false;
            }

            if ($locked->status !== OrderStatus::SHIPPING_CREATED) {
                return null;
            }

            $shipment = DB::table('shipments')->where('order_id', (int) $id)->lockForUpdate()->first();
            if (!$shipment || !$shipment->tracking_no) {
                return null;
            }

            DB::table('shipments')->where('order_id', (int) $id)->update([
                'status' => 'shipped',
                'updated_at' => now(),
            ]);

            DB::table('orders')->where('id', (int) $id)->update([
                'status' => OrderStatus::SHIPPED,
                'updated_at' => now(),
            ]);

            OrderEventLogger::emit('order.shipped', (int) $id, (int) $locked->user_id, ['tracking_no' => $shipment->tracking_no, 'order_no' => $order->order_no]);

            return true;
        });

        if ($updated === false) {
            return response()->json(['ok' => false, 'message' => 'order not found'], 404);
        }

        if ($updated === null) {
            return ApiError::orderStateConflict('สถานะออเดอร์ไม่ถูกต้อง กรุณารีเฟรช');
        }

        return response()->json(['ok' => true, 'order' => DB::table('orders')->where('id', (int) $id)->first()]);
    }

    public function cancelOrder(Request $request, $id)
    {
        $ownerId = (int) $request->user()->id;
        $order = $this->getOwnedOrderOrError((int) $id, $ownerId);
        if ($order instanceof \Illuminate\Http\JsonResponse) {
            return $order;
        }

        if (!in_array($order->status, [OrderStatus::WAITING_PAYMENT, OrderStatus::PAYMENT_REVIEW, OrderStatus::PAYMENT_REJECTED], true)) {
            return ApiError::orderStateConflict('สถานะออเดอร์ไม่ถูกต้อง กรุณารีเฟรช', ['order_status' => $order->status]);
        }

        DB::table('orders')->where('id', (int) $id)->update([
            'status' => OrderStatus::CANCELLED,
            'cancelled_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true, 'order' => DB::table('orders')->where('id', (int) $id)->first()]);
    }

    private function getOwnedOrderOrError(int $orderId, int $ownerId)
    {
        $order = DB::table('orders')
            ->join('merchants', 'merchants.id', '=', 'orders.merchant_id')
            ->where('orders.id', $orderId)
            ->select(['orders.*', 'merchants.owner_user_id'])
            ->first();

        if (!$order) {
            return response()->json(['ok' => false, 'message' => 'order not found'], 404);
        }

<<<<<<< HEAD
        if ((int) $order->owner_user_id !== $ownerId) {
            return ApiError::forbidden('ไม่มีสิทธิ์');
        }

        return $order;
=======
        $shipmentPayload = [
            'provider' => $data['provider'],
            'tracking_no' => $data['tracking_no'],
            'fee' => round((float) ($data['fee'] ?? 0), 2),
            'updated_at' => now(),
        ];

        $exists = DB::table('shipments')->where('order_id', (int) $id)->exists();
        if ($exists) {
            DB::table('shipments')->where('order_id', (int) $id)->update($shipmentPayload);
        } else {
            DB::table('shipments')->insert(array_merge($shipmentPayload, ['order_id' => (int) $id, 'created_at' => now()]));
        }

        DB::table('orders')->where('id', (int) $id)->update([
            'status' => OrderStatus::SHIPPING_CREATED,
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true, 'shipment' => DB::table('shipments')->where('order_id', (int) $id)->first()]);
>>>>>>> origin/main
    }
}
