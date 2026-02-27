<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\PosDevice;

class PosDeviceController extends Controller
{
    // GET /api/pos/me
    public function me(Request $request)
    {
        $actor = $request->user();

        if (!($actor instanceof PosDevice)) {
            return response()->json([
                'ok' => false,
                'error' => 'POS_DEVICE_TOKEN_REQUIRED',
            ], 401);
        }

        // audit last seen (แม้ revoked ก็ยังอัปเดตได้เพื่อรู้ว่ามีใครพยายามใช้)
        $actor->forceFill(['last_seen_at' => now()])->save();
        $actor->refresh();

        $merchant = DB::table('merchants')
            ->where('id', (int)$actor->merchant_id)
            ->select(['id','status','owner_user_id'])
            ->first();

        $token = $request->user()->currentAccessToken();
        $abilities = $token ? ($token->abilities ?? []) : [];

        $status = $actor->revoked_at ? 'revoked' : 'active';

        return response()->json([
            'ok' => true,
            'status' => $status,
            'can_operate' => $status === 'active',
            'device' => [
                'id' => (int)$actor->id,
                'merchant_id' => (int)$actor->merchant_id,
                'name' => $actor->name,
                'device_uid' => $actor->device_uid,
                'last_seen_at' => $actor->last_seen_at,
                'revoked_at' => $actor->revoked_at,
                'created_by_user_id' => $actor->created_by_user_id,
            ],
            'merchant' => $merchant,
            'abilities' => $abilities,
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
