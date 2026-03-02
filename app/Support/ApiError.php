<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

final class ApiError
{
    public static function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'code' => 'FORBIDDEN_RESOURCE',
        ], 403);
    }

    public static function orderStateConflict(string $message, array $extra = []): JsonResponse
    {
        return response()->json(array_merge([
            'message' => $message,
            'code' => 'ORDER_STATE_CONFLICT',
        ], $extra), 409);
    }
}
