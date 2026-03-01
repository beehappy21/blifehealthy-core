<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminOrderController extends Controller
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
        $status = strtoupper((string) $request->query('status', ''));
        $q = trim((string) $request->query('q', ''));

        $query = DB::table('orders')
            ->leftJoin('payment_slips', 'payment_slips.order_id', '=', 'orders.id')
            ->leftJoin('shipments', 'shipments.order_id', '=', 'orders.id')
            ->leftJoin('users as customers', 'customers.id', '=', 'orders.user_id')
            ->leftJoin('merchants', 'merchants.id', '=', 'orders.merchant_id')
            ->select([
                'orders.id',
                'orders.order_no',
                'orders.status',
                'orders.total',
                'orders.paid_at',
                'orders.created_at',
                'customers.name as customer_name',
                'customers.phone as customer_phone',
                'merchants.shop_name as merchant_shop_name',
                'payment_slips.status as payment_slip_status',
                'payment_slips.image_url as payment_slip_image_url',
                'shipments.provider as shipment_provider',
                'shipments.tracking_no as shipment_tracking_no',
            ])
            ->orderByDesc('orders.id')
            ->limit(200);

        if ($status !== '' && in_array($status, self::ALLOWED_STATUSES, true)) {
            $query->where('orders.status', $status);
        }

        if ($q !== '') {
            $query->where(function ($subQuery) use ($q) {
                $subQuery->where('orders.order_no', 'like', '%' . $q . '%')
                    ->orWhere('customers.name', 'like', '%' . $q . '%')
                    ->orWhere('customers.phone', 'like', '%' . $q . '%')
                    ->orWhere('shipments.tracking_no', 'like', '%' . $q . '%')
                    ->orWhere('merchants.shop_name', 'like', '%' . $q . '%');
            });
        }

        return response()->json(['ok' => true, 'items' => $query->get()]);
    }

    public function show($id)
    {
        $order = DB::table('orders')
            ->leftJoin('users as customers', 'customers.id', '=', 'orders.user_id')
            ->leftJoin('merchants', 'merchants.id', '=', 'orders.merchant_id')
            ->where('orders.id', (int) $id)
            ->select([
                'orders.*',
                'customers.name as customer_name',
                'customers.phone as customer_phone',
                'merchants.shop_name as merchant_shop_name',
            ])
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
        $data = $request->validate([
            'status' => ['required', 'string', 'in:' . implode(',', self::ALLOWED_STATUSES)],
        ]);

        $updated = DB::table('orders')->where('id', (int) $id)->update([
            'status' => $data['status'],
            'updated_at' => now(),
        ]);

        if (!$updated) {
            return response()->json(['ok' => false, 'message' => 'order not found'], 404);
        }

        return response()->json([
            'ok' => true,
            'order' => DB::table('orders')->where('id', (int) $id)->first(),
        ]);
    }
}
