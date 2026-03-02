<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Enums\PaymentSlipStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MerchantPaymentSlipController extends Controller
{
    public function index(Request $request)
    {
        $ownerId = (int) $request->user()->id;
        $status = PaymentSlipStatus::normalize((string) $request->query('status', ''));

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

        if ($status !== '' && in_array($status, PaymentSlipStatus::values(), true)) {
            $query->where('payment_slips.status', $status);
        }

        return response()->json(['ok' => true, 'items' => $query->get()]);
    }

    public function approve(Request $request, $id)
    {
        $ownerId = (int) $request->user()->id;
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        $result = DB::transaction(function () use ($id, $ownerId, $data) {
            $row = DB::table('payment_slips')
                ->join('orders', 'orders.id', '=', 'payment_slips.order_id')
                ->join('merchants', 'merchants.id', '=', 'orders.merchant_id')
                ->where('payment_slips.id', (int) $id)
                ->where('merchants.owner_user_id', $ownerId)
                ->select(['payment_slips.id as slip_id', 'orders.id as order_id'])
                ->lockForUpdate()
                ->first();

            if (!$row) {
                return null;
            }

            DB::table('payment_slips')->where('id', (int) $row->slip_id)->update([
                'status' => PaymentSlipStatus::APPROVED,
                'admin_note' => $data['note'] ?? null,
                'reviewed_by' => $ownerId,
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);

            $latestSlip = DB::table('payment_slips')
                ->where('order_id', (int) $row->order_id)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($latestSlip) {
                DB::table('orders')->where('id', (int) $row->order_id)->update([
                    'status' => $latestSlip->status === PaymentSlipStatus::APPROVED ? OrderStatus::PAID : OrderStatus::PAYMENT_REJECTED,
                    'paid_at' => $latestSlip->status === PaymentSlipStatus::APPROVED ? now() : null,
                    'updated_at' => now(),
                ]);
            }

            return DB::table('payment_slips')->where('id', (int) $row->slip_id)->first();
        });

        if (!$result) {
            return response()->json(['ok' => false, 'message' => 'payment slip not found'], 404);
        }

        return response()->json(['ok' => true, 'item' => $result]);
    }

    public function reject(Request $request, $id)
    {
        $ownerId = (int) $request->user()->id;
        $data = $request->validate(['note' => ['required', 'string', 'max:500']]);

        $result = DB::transaction(function () use ($id, $ownerId, $data) {
            $row = DB::table('payment_slips')
                ->join('orders', 'orders.id', '=', 'payment_slips.order_id')
                ->join('merchants', 'merchants.id', '=', 'orders.merchant_id')
                ->where('payment_slips.id', (int) $id)
                ->where('merchants.owner_user_id', $ownerId)
                ->select(['payment_slips.id as slip_id', 'orders.id as order_id'])
                ->lockForUpdate()
                ->first();

            if (!$row) {
                return null;
            }

            DB::table('payment_slips')->where('id', (int) $row->slip_id)->update([
                'status' => PaymentSlipStatus::REJECTED,
                'admin_note' => $data['note'],
                'reviewed_by' => $ownerId,
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);

            $latestSlip = DB::table('payment_slips')
                ->where('order_id', (int) $row->order_id)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($latestSlip) {
                DB::table('orders')->where('id', (int) $row->order_id)->update([
                    'status' => $latestSlip->status === PaymentSlipStatus::APPROVED ? OrderStatus::PAID : OrderStatus::PAYMENT_REJECTED,
                    'paid_at' => $latestSlip->status === PaymentSlipStatus::APPROVED ? now() : null,
                    'updated_at' => now(),
                ]);
            }

            return DB::table('payment_slips')->where('id', (int) $row->slip_id)->first();
        });

        if (!$result) {
            return response()->json(['ok' => false, 'message' => 'payment slip not found'], 404);
        }

        return response()->json(['ok' => true, 'item' => $result]);
    }
}
