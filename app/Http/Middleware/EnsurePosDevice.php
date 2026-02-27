<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\PosDevice;

class EnsurePosDevice
{
    public function handle(Request $request, Closure $next, ?string $ability = null)
    {
        $actor = $request->user();

        if (!($actor instanceof PosDevice)) {
            return response()->json([
                'ok' => false,
                'error' => 'POS_DEVICE_TOKEN_REQUIRED',
            ], 401);
        }

        if ($actor->revoked_at) {
            return response()->json([
                'ok' => false,
                'error' => 'POS_DEVICE_REVOKED',
            ], 401);
        }

        if ($ability && !$request->user()->tokenCan($ability)) {
            return response()->json([
                'ok' => false,
                'error' => 'MISSING_ABILITY',
                'required' => $ability,
            ], 403);
        }

        // audit: last seen
        $actor->forceFill(['last_seen_at' => now()])->save();

        return $next($request);
    }
}