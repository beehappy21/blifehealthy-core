<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Enums\PaymentSlipStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Support\ApiError;
use Illuminate\Support\Facades\DB;

class AdminPaymentSlipController extends Controller
{
    public function index(Request $request)
    {
        $status = PaymentSlipStatus::normalize((string) $request->query('status', ''));

        $query = DB::table('payment_slips')
            ->join('orders', 'orders.id', '=', 'payment_slips.order_id')
            ->leftJoin('merchants', 'merchants.id', '=', 'orders.merchant_id')
            ->select([
                'payment_slips.*',
                'orders.order_no',
                'orders.status as order_status',
                'orders.total as order_total',
                'orders.paid_at',
                'merchants.shop_name as merchant_shop_name',
            ])
            ->orderByDesc('payment_slips.id')
            ->limit(200);

        if ($status !== '' && in_array($status, PaymentSlipStatus::values(), true)) {
            $query->where('payment_slips.status', $status);
        }

        return response()->json(['ok' => true, 'items' => $query->get()]);
    }

    public function approve(Request $request, $id)
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:255']]);
        return $this->reviewSlip((int) $id, (int) $request->user()->id, PaymentSlipStatus::APPROVED, $data['note'] ?? null);
    }

    public function reject(Request $request, $id)
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:255']]);
        return $this->reviewSlip((int) $id, (int) $request->user()->id, PaymentSlipStatus::REJECTED, $data['note']);
    }

    private function reviewSlip(int $slipId, int $reviewerId, string $decisionStatus, ?string $note)
    {
        $slip = DB::table('payment_slips')
            ->join('orders', 'orders.id', '=', 'payment_slips.order_id')
            ->where('payment_slips.id', $slipId)
            ->select([
                'payment_slips.id as slip_id',
                'orders.id as order_id',
                'orders.status as order_status',
            ])
            ->first();

        if (!$slip) {
            return response()->json(['ok' => false, 'message' => 'payment slip not found'], 404);
        }

        if ($slip->order_status !== OrderStatus::PAYMENT_REVIEW) {
            return ApiError::orderStateConflict('สถานะออเดอร์ไม่ถูกต้อง/มีสลิปใหม่กว่า กรุณารีเฟรช', ['order_status' => $slip->order_status]);
        }

        $item = DB::transaction(function () use ($slip, $reviewerId, $decisionStatus, $note) {
            DB::table('orders')->where('id', (int) $slip->order_id)->lockForUpdate()->first();
            DB::table('payment_slips')->where('id', (int) $slip->slip_id)->lockForUpdate()->first();

            DB::table('payment_slips')->where('id', (int) $slip->slip_id)->update([
                'status' => $decisionStatus,
                'admin_note' => $note,
                'reviewed_by' => $reviewerId,
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

            return DB::table('payment_slips')->where('id', (int) $slip->slip_id)->first();
        });

        if (is_array($item) && ($item['conflict'] ?? false)) {
            return ApiError::orderStateConflict('Only latest slip can be reviewed');
        }

        return response()->json(['ok' => true, 'item' => $item]);
    }
}
