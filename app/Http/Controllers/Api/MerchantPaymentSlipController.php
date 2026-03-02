<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Enums\PaymentSlipStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Support\ApiError;
use App\Support\OrderEventLogger;
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
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        return $this->reviewSlip($request, (int) $id, PaymentSlipStatus::APPROVED, $data['note'] ?? null);
    }

    public function reject(Request $request, $id)
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:500']]);
        return $this->reviewSlip($request, (int) $id, PaymentSlipStatus::REJECTED, $data['note']);
    }

    private function reviewSlip(Request $request, int $slipId, string $decisionStatus, ?string $note)
    {
        $ownerId = (int) $request->user()->id;

        $slip = DB::table('payment_slips')
            ->join('orders', 'orders.id', '=', 'payment_slips.order_id')
            ->join('merchants', 'merchants.id', '=', 'orders.merchant_id')
            ->where('payment_slips.id', $slipId)
            ->select([
                'payment_slips.id as slip_id',
                'orders.id as order_id',
                'orders.status as order_status',
                'merchants.owner_user_id as owner_user_id',
            ])
            ->first();

        if (!$slip) {
            return response()->json(['ok' => false, 'message' => 'payment slip not found'], 404);
        }

        if ((int) $slip->owner_user_id !== $ownerId) {
            return ApiError::forbidden('ไม่มีสิทธิ์');
        }

        if ($slip->order_status !== OrderStatus::PAYMENT_REVIEW) {
            return ApiError::orderStateConflict('สถานะออเดอร์ไม่ถูกต้อง/มีสลิปใหม่กว่า กรุณารีเฟรช', ['order_status' => $slip->order_status]);
        }

        $result = DB::transaction(function () use ($slip, $ownerId, $decisionStatus, $note) {
            DB::table('orders')->where('id', (int) $slip->order_id)->lockForUpdate()->first();
            DB::table('payment_slips')->where('id', (int) $slip->slip_id)->lockForUpdate()->first();

            DB::table('payment_slips')->where('id', (int) $slip->slip_id)->update([
                'status' => $decisionStatus,
                'admin_note' => $note,
                'reviewed_by' => $ownerId,
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);

            $latestSlip = DB::table('payment_slips')
                ->where('order_id', (int) $slip->order_id)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (!$latestSlip || (int) $latestSlip->id !== (int) $slip->slip_id) {
                return ['conflict' => true];
            }

            DB::table('orders')->where('id', (int) $slip->order_id)->update([
                'status' => $latestSlip->status === PaymentSlipStatus::APPROVED ? OrderStatus::PAID : OrderStatus::PAYMENT_REJECTED,
                'paid_at' => $latestSlip->status === PaymentSlipStatus::APPROVED ? now() : null,
                'updated_at' => now(),
            ]);

            $orderRow = DB::table('orders')->where('id', (int) $slip->order_id)->first();
            if ($orderRow) {
                OrderEventLogger::emit(
                    $latestSlip->status === PaymentSlipStatus::APPROVED ? 'slip.approved' : 'slip.rejected',
                    (int) $slip->order_id,
                    (int) $orderRow->user_id,
                    ['status' => $orderRow->status, 'order_no' => $orderRow->order_no]
                );
            }

            return DB::table('payment_slips')->where('id', (int) $slip->slip_id)->first();
        });

        if (is_array($result) && ($result['conflict'] ?? false)) {
            return ApiError::orderStateConflict('Only latest slip can be reviewed');
        }

        return response()->json(['ok' => true, 'item' => $result]);
    }
}
