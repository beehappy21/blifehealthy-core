<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MerchantPaymentSlipController extends Controller
{
    public function index(Request $request)
    {
        $ownerId = (int)$request->user()->id;
        $status = strtolower((string)$request->query('status', ''));

        $statusMap = [
            'pending' => 'submitted',
            'submitted' => 'submitted',
            'approved' => 'approved',
            'rejected' => 'rejected',
        ];

        $query = DB::table('payment_slips')
            ->join('orders', 'orders.id', '=', 'payment_slips.order_id')
            ->join('merchants', 'merchants.id', '=', 'orders.merchant_id')
            ->where('merchants.owner_user_id', $ownerId)
            ->select([
                'payment_slips.id',
                'payment_slips.order_id',
                'payment_slips.image_url',
                'payment_slips.amount',
                'payment_slips.transfer_at',
                'payment_slips.status as slip_status',
                'payment_slips.admin_note',
                'payment_slips.reviewed_by',
                'payment_slips.reviewed_at',
                'payment_slips.created_at as slip_created_at',
                'payment_slips.updated_at as slip_updated_at',
                'orders.order_no',
                'orders.status as order_status',
                'orders.total as order_total',
                'orders.paid_at',
            ])
            ->orderByDesc('payment_slips.id')
            ->limit(100);

        if ($status !== '' && isset($statusMap[$status])) {
            $query->where('payment_slips.status', $statusMap[$status]);
        }

        $items = $query->get();

        return response()->json(['ok' => true, 'items' => $items]);
    }

    public function approve(Request $request, $id)
    {
        $ownerId = (int)$request->user()->id;
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $result = DB::transaction(function () use ($id, $ownerId, $data) {
            $row = DB::table('payment_slips')
                ->join('orders', 'orders.id', '=', 'payment_slips.order_id')
                ->join('merchants', 'merchants.id', '=', 'orders.merchant_id')
                ->where('payment_slips.id', (int)$id)
                ->where('merchants.owner_user_id', $ownerId)
                ->select(['payment_slips.id as slip_id', 'orders.id as order_id'])
                ->lockForUpdate()
                ->first();

            if (!$row) {
                return null;
            }

            DB::table('payment_slips')
                ->where('id', (int)$row->slip_id)
                ->update([
                    'status' => 'approved',
                    'admin_note' => $data['note'] ?? null,
                    'reviewed_by' => $ownerId,
                    'reviewed_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('orders')
                ->where('id', (int)$row->order_id)
                ->update([
                    'status' => 'PAID',
                    'paid_at' => now(),
                    'updated_at' => now(),
                ]);

            return DB::table('payment_slips')->where('id', (int)$row->slip_id)->first();
        });

        if (!$result) {
            return response()->json(['ok' => false, 'message' => 'payment slip not found'], 404);
        }

        return response()->json(['ok' => true, 'item' => $result]);
    }

    public function reject(Request $request, $id)
    {
        $ownerId = (int)$request->user()->id;
        $data = $request->validate([
            'note' => ['required', 'string', 'max:500'],
        ]);

        $result = DB::transaction(function () use ($id, $ownerId, $data) {
            $row = DB::table('payment_slips')
                ->join('orders', 'orders.id', '=', 'payment_slips.order_id')
                ->join('merchants', 'merchants.id', '=', 'orders.merchant_id')
                ->where('payment_slips.id', (int)$id)
                ->where('merchants.owner_user_id', $ownerId)
                ->select(['payment_slips.id as slip_id', 'orders.id as order_id'])
                ->lockForUpdate()
                ->first();

            if (!$row) {
                return null;
            }

            DB::table('payment_slips')
                ->where('id', (int)$row->slip_id)
                ->update([
                    'status' => 'rejected',
                    'admin_note' => $data['note'],
                    'reviewed_by' => $ownerId,
                    'reviewed_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('orders')
                ->where('id', (int)$row->order_id)
                ->update([
                    'status' => 'PAYMENT_REJECTED',
                    'updated_at' => now(),
                ]);

            return DB::table('payment_slips')->where('id', (int)$row->slip_id)->first();
        });

        if (!$result) {
            return response()->json(['ok' => false, 'message' => 'payment slip not found'], 404);
        }

        return response()->json(['ok' => true, 'item' => $result]);
    }
}
