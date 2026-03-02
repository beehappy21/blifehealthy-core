<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Enums\PaymentSlipStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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
        $result = $this->reviewSlip((int) $id, (int) $request->user()->id, PaymentSlipStatus::APPROVED, $data['note'] ?? null);

        if (!$result) {
            return response()->json(['ok' => false, 'message' => 'payment slip not found'], 404);
        }

        return response()->json(['ok' => true, 'item' => $result]);
    }

    public function reject(Request $request, $id)
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:255']]);
        $result = $this->reviewSlip((int) $id, (int) $request->user()->id, PaymentSlipStatus::REJECTED, $data['note']);

        if (!$result) {
            return response()->json(['ok' => false, 'message' => 'payment slip not found'], 404);
        }

        return response()->json(['ok' => true, 'item' => $result]);
    }

    private function reviewSlip(int $slipId, int $reviewerId, string $status, ?string $note): ?object
    {
        return DB::transaction(function () use ($slipId, $reviewerId, $status, $note) {
            $row = DB::table('payment_slips')->where('id', $slipId)->select(['id', 'order_id'])->lockForUpdate()->first();
            if (!$row) {
                return null;
            }

            DB::table('payment_slips')->where('id', $slipId)->update([
                'status' => $status,
                'admin_note' => $note,
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);

            $latestSlip = DB::table('payment_slips')
                ->where('order_id', (int) $row->order_id)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($latestSlip) {
                $orderUpdate = [
                    'status' => $latestSlip->status === PaymentSlipStatus::APPROVED ? OrderStatus::PAID : OrderStatus::PAYMENT_REJECTED,
                    'updated_at' => now(),
                ];

                if ($latestSlip->status === PaymentSlipStatus::APPROVED) {
                    $orderUpdate['paid_at'] = now();
                }

                DB::table('orders')->where('id', (int) $row->order_id)->update($orderUpdate);
            }

            return DB::table('payment_slips')->where('id', $slipId)->first();
        });
    }
}
