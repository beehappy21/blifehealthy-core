<?php

namespace App\Http\Middleware;

use Closure;
use App\Support\ApiError;
use Illuminate\Http\Request;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if (!$user->hasRole('admin')) {
            return ApiError::forbidden('ไม่มีสิทธิ์');
        }

        return $next($request);
    }
}
