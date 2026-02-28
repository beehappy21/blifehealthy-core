<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = DB::table('orders')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->get();

        return response()->json(['ok' => true, 'items' => $orders]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'address_id' => ['required', 'integer', 'exists:user_addresses,id'],
            'shipping_fee' => ['nullable', 'numeric', 'min:0'],
            'shipping_provider' => ['nullable', 'string', 'max:50'],
            'shipping_option_json' => ['nullable', 'array'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
        ]);

        $userId = (int)$request->user()->id;

        $address = DB::table('user_addresses')
            ->where('id', (int)$data['address_id'])
            ->where('user_id', $userId)
            ->first();

        if (!$address) {
            return response()->json(['ok' => false, 'message' => 'address not found'], 404);
        }

        $shippingFee = (float)($data['shipping_fee'] ?? 0);

        $result = DB::transaction(function () use ($data, $userId, $shippingFee) {
            $merchantId = null;
            $subtotal = 0.0;
            $lineItems = [];

            foreach ($data['items'] as $item) {
                $variant = DB::table('product_variants')
                    ->join('products', 'products.id', '=', 'product_variants.product_id')
                    ->join('merchants', 'merchants.id', '=', 'products.merchant_id')
                    ->where('product_variants.id', (int)$item['variant_id'])
                    ->where('product_variants.product_id', (int)$item['product_id'])
                    ->where('product_variants.status', 'active')
                    ->where('products.status', 'active')
                    ->where('merchants.status', 'approved')
                    ->select([
                        'product_variants.id as variant_id',
                        'product_variants.product_id',
                        'product_variants.sku',
                        'product_variants.option_json',
                        'product_variants.price',
                        'product_variants.stock_qty',
                        'products.merchant_id',
                    ])
                    ->lockForUpdate()
                    ->first();

                if (!$variant) {
                    throw ValidationException::withMessages(['items' => 'variant not available']);
                }

                if ($merchantId === null) {
                    $merchantId = (int)$variant->merchant_id;
                }

                if ($merchantId !== (int)$variant->merchant_id) {
                    throw ValidationException::withMessages(['items' => 'all items must belong to same merchant']);
                }

                $qty = (int)$item['qty'];
                if ((int)$variant->stock_qty < $qty) {
                    throw ValidationException::withMessages(['items' => 'insufficient stock for variant ' . $variant->variant_id]);
                }

                $unitPrice = (float)$variant->price;
                $lineTotal = $unitPrice * $qty;
                $subtotal += $lineTotal;

                $lineItems[] = [
                    'product_id' => (int)$variant->product_id,
                    'variant_id' => (int)$variant->variant_id,
                    'sku' => $variant->sku,
                    'option_snapshot_json' => $variant->option_json,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];

                DB::table('product_variants')
                    ->where('id', (int)$variant->variant_id)
                    ->update([
                        'stock_qty' => (int)$variant->stock_qty - $qty,
                        'updated_at' => now(),
                    ]);
            }

            $total = $subtotal + $shippingFee;
            $orderNo = $this->nextOrderNo();

            $orderId = DB::table('orders')->insertGetId([
                'order_no' => $orderNo,
                'user_id' => $userId,
                'merchant_id' => (int)$merchantId,
                'status' => 'WAITING_PAYMENT',
                'subtotal' => round($subtotal, 2),
                'shipping_fee' => round($shippingFee, 2),
                'total' => round($total, 2),
                'shipping_provider' => $data['shipping_provider'] ?? null,
                'shipping_option_json' => isset($data['shipping_option_json']) ? json_encode($data['shipping_option_json'], JSON_UNESCAPED_UNICODE) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($lineItems as $line) {
                DB::table('order_items')->insert([
                    'order_id' => $orderId,
                    'product_id' => $line['product_id'],
                    'variant_id' => $line['variant_id'],
                    'sku' => $line['sku'],
                    'option_snapshot_json' => $line['option_snapshot_json'],
                    'qty' => $line['qty'],
                    'unit_price' => round($line['unit_price'], 2),
                    'line_total' => round($line['line_total'], 2),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return DB::table('orders')->where('id', $orderId)->first();
        });

        return response()->json(['ok' => true, 'item' => $result], 201);
    }

    public function show(Request $request, $id)
    {
        $order = DB::table('orders')
            ->where('id', (int)$id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$order) {
            return response()->json(['ok' => false, 'message' => 'order not found'], 404);
        }

        $items = DB::table('order_items')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->where('order_items.order_id', (int)$id)
            ->orderBy('order_items.id')
            ->select([
                'order_items.*',
                'products.name as product_name',
            ])
            ->get();

        $slip = DB::table('payment_slips')->where('order_id', (int)$id)->first();
        $shipment = DB::table('shipments')->where('order_id', (int)$id)->first();

        return response()->json([
            'ok' => true,
            'order' => $order,
            'items' => $items,
            'payment_slip' => $slip,
            'shipment' => $shipment,
        ]);
    }

    public function uploadSlip(Request $request, $id)
    {
        $order = DB::table('orders')
            ->where('id', (int)$id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$order) {
            return response()->json(['ok' => false, 'message' => 'order not found'], 404);
        }

        $data = $request->validate([
            'slip' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'transfer_at' => ['nullable', 'date'],
        ]);

        $path = $request->file('slip')->store('payment-slips', 'public');
        $url = Storage::url($path);

        $payload = [
            'order_id' => (int)$id,
            'image_url' => $url,
            'amount' => round((float)($data['amount'] ?? $order->total), 2),
            'transfer_at' => $data['transfer_at'] ?? null,
            'status' => 'submitted',
            'admin_note' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'updated_at' => now(),
        ];

        $exists = DB::table('payment_slips')->where('order_id', (int)$id)->exists();
        if ($exists) {
            DB::table('payment_slips')->where('order_id', (int)$id)->update($payload);
        } else {
            $payload['created_at'] = now();
            DB::table('payment_slips')->insert($payload);
        }

        DB::table('orders')->where('id', (int)$id)->update(['status' => 'PAYMENT_REVIEW', 'updated_at' => now()]);

        $slip = DB::table('payment_slips')->where('order_id', (int)$id)->first();

        return response()->json(['ok' => true, 'item' => $slip]);
    }

    private function nextOrderNo(): string
    {
        return DB::transaction(function () {
            $row = DB::table('sequences')->where('key', 'order_no')->lockForUpdate()->first();

            if (!$row) {
                $current = 1;
                DB::table('sequences')->insert([
                    'key' => 'order_no',
                    'current_value' => $current,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $current = (int)$row->current_value + 1;
                DB::table('sequences')->where('key', 'order_no')->update([
                    'current_value' => $current,
                    'updated_at' => now(),
                ]);
            }

            return 'ORD' . now()->format('Ymd') . str_pad((string)$current, 6, '0', STR_PAD_LEFT);
        });
    }
}
