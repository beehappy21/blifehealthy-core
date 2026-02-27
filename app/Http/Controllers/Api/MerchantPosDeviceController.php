<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PosDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MerchantPosDeviceController extends Controller
{
    private function myApprovedMerchantOrFail($user)
    {
        $m = DB::table('merchants')->where('owner_user_id', (int)$user->id)->first();
        if (!$m) return [null, response()->json(['ok' => false, 'error' => 'MERCHANT_NOT_FOUND', 'message' => 'merchant not found'], 404)];
        if ($m->status !== 'approved') return [null, response()->json(['ok' => false, 'error' => 'MERCHANT_NOT_APPROVED', 'message' => 'merchant not approved'], 403)];
        return [$m, null];
    }

    private function activeTokenCountForDevice(int $deviceId): int
    {
        // tokenable_type เก็บเป็น "App\\Models\\PosDevice" ใน DB
        return (int) DB::table('personal_access_tokens')
            ->where('tokenable_type', PosDevice::class)
            ->where('tokenable_id', $deviceId)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->count();
    }

    public function index(Request $request)
    {
        [$merchant, $err] = $this->myApprovedMerchantOrFail($request->user());
        if ($err) return $err;

        $devices = PosDevice::where('merchant_id', (int)$merchant->id)
            ->orderByDesc('id')
            ->get([
                'id',
                'merchant_id',
                'name',
                'device_uid',
                'last_seen_at',
                'revoked_at',
                'created_by_user_id',
                'created_at',
                'updated_at',
            ])
            ->map(function ($d) {
                $d->status = $d->revoked_at ? 'revoked' : 'active';
                $d->active_token_count = $d->revoked_at ? 0 : $this->activeTokenCountForDevice((int)$d->id);
                return $d;
            });

        return response()->json(['ok' => true, 'devices' => $devices]);
    }

    public function store(Request $request)
    {
        [$merchant, $err] = $this->myApprovedMerchantOrFail($request->user());
        if ($err) return $err;

        $data = $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $device = PosDevice::create([
            'merchant_id' => (int)$merchant->id,
            'name' => $data['name'],
            'device_uid' => (string) Str::uuid(),
            'created_by_user_id' => (int)$request->user()->id,
        ]);

        $token = $device->createToken('pos-device', ['pos:lookup', 'pos:scan', 'pos:redeem'])->plainTextToken;

        return response()->json([
            'ok' => true,
            'device' => $device,
            'token' => $token, // คืนครั้งเดียว
        ], 201);
    }

    public function rotateToken(Request $request, $id)
    {
        [$merchant, $err] = $this->myApprovedMerchantOrFail($request->user());
        if ($err) return $err;

        $device = PosDevice::where('merchant_id', (int)$merchant->id)->findOrFail($id);

        if ($device->revoked_at) {
            return response()->json([
                'ok' => false,
                'error' => 'DEVICE_REVOKED',
                'message' => 'device is revoked',
            ], 409);
        }

        // ลบ token เก่าทั้งหมดของ device นี้ (ทำให้เครื่องเดิมใช้ไม่ได้ทันที)
        $device->tokens()->delete();

        $token = $device->createToken('pos-device', ['pos:lookup', 'pos:scan', 'pos:redeem'])->plainTextToken;

        return response()->json([
            'ok' => true,
            'device_id' => (int)$device->id,
            'token' => $token, // คืนครั้งเดียว
        ]);
    }

    public function revoke(Request $request, $id)
    {
        [$merchant, $err] = $this->myApprovedMerchantOrFail($request->user());
        if ($err) return $err;

        $device = PosDevice::where('merchant_id', (int)$merchant->id)->findOrFail($id);

        if (!$device->revoked_at) {
            $device->forceFill(['revoked_at' => now()])->save();
        $device->refresh();
        }

        // ลบ token ทั้งหมด → เครื่องใช้ต่อไม่ได้

        // NOTE: ไม่ลบ token เพื่อให้ POS เห็น POS_DEVICE_REVOKED (ถูกบล็อกโดย middleware pos.device)

        return response()->json([
            'ok' => true,
            'device_id' => (int)$device->id,
            'revoked_at' => $device->revoked_at,
        ]);
    }
}