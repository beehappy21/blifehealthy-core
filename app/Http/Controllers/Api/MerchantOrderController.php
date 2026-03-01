<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MerchantOrderController extends Controller
{
    private const ALLOWED_STATUSES = [
        'WAITING_PAYMENT',
        'PAYMENT_REVIEW',
        'PAYMENT_REJECTED',
        'PAID',
        'SHIPPING_CREATED',
        'SHIPPED',
        'CANCELLED',
    ];

    public function index(Request $request)
    {
        $ownerId = (int) $request->user()->id;
        $status = strtoupper((string) $request->query('status', ''));
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

        if ($status !== '' && in_array($status, self::ALLOWED_STATUSES, true)) {
            $query->where('orders.status', $status);
        }

        if ($q !== '') {
            $query->where(function ($subQuery) use ($q) {
                $subQuery->where('orders.order_no', 'like', '%' . $q . '%')
                    ->orWhere('shipments.tracking_no', 'like', '%' . $q . '%')
                    ->orWhere('payment_slips.status', 'like', '%' . strtolower($q) . '%');
            });
        }

        return response()->json([
            'ok' => true,
            'items' => $query->get(),
        ]);
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
            return response()->json(['ok' => false, 'message' => 'order not found'], 404);
        }

        $items = DB::table('order_items')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->where('order_items.order_id', (int) $id)
            ->orderBy('order_items.id')
            ->select([
                'order_items.*',
                'products.name as product_name',
            ])
            ->get();

        $paymentSlip = DB::table('payment_slips')->where('order_id', (int) $id)->first();
        $shipment = DB::table('shipments')->where('order_id', (int) $id)->first();

        return response()->json([
            'ok' => true,
            'order' => $order,
            'items' => $items,
            'payment_slip' => $paymentSlip,
            'shipment' => $shipment,
            'address_snapshot' => null,
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $ownerId = (int) $request->user()->id;
        $status = strtoupper((string) $request->input('status', ''));

        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            return response()->json(['ok' => false, 'message' => 'invalid status'], 422);
        }

        $updated = DB::table('orders')
            ->join('merchants', 'merchants.id', '=', 'orders.merchant_id')
            ->where('orders.id', (int) $id)
            ->where('merchants.owner_user_id', $ownerId)
            ->update([
                'orders.status' => $status,
                'orders.updated_at' => now(),
            ]);

        if (!$updated) {
            return response()->json(['ok' => false, 'message' => 'order not found'], 404);
        }

        $order = DB::table('orders')->where('id', (int) $id)->first();

        return response()->json(['ok' => true, 'order' => $order]);
    }

    public function upsertShipment(Request $request, $id)
    {
        $ownerId = (int) $request->user()->id;

        $data = $request->validate([
            'provider' => ['required', 'string', 'max:50'],
            'tracking_no' => ['required', 'string', 'max:100'],
            'fee' => ['nullable', 'numeric', 'min:0'],
        ]);

        $order = DB::table('orders')
            ->join('merchants', 'merchants.id', '=', 'orders.merchant_id')
            ->where('orders.id', (int) $id)
            ->where('merchants.owner_user_id', $ownerId)
            ->select('orders.id')
            ->first();

        if (!$order) {
            return response()->json(['ok' => false, 'message' => 'order not found'], 404);
        }

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
            DB::table('shipments')->insert(array_merge($shipmentPayload, [
                'order_id' => (int) $id,
                'created_at' => now(),
            ]));
        }

        DB::table('orders')->where('id', (int) $id)->update([
            'status' => 'SHIPPING_CREATED',
            'updated_at' => now(),
        ]);

        $shipment = DB::table('shipments')->where('order_id', (int) $id)->first();

        return response()->json(['ok' => true, 'shipment' => $shipment]);
    }
}
