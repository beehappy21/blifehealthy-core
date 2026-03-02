<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class OrderEventLogger
{
    public static function emit(string $eventType, int $orderId, int $userId, array $payload = []): void
    {
        if (!DB::getSchemaBuilder()->hasTable('integration_outbox')) {
            return;
        }

        DB::table('integration_outbox')->insert([
            'event_type' => $eventType,
            'payload_json' => json_encode(array_merge([
                'order_id' => $orderId,
                'user_id' => $userId,
            ], $payload), JSON_UNESCAPED_UNICODE),
            'status' => 'pending',
            'retry_count' => 0,
            'next_retry_at' => null,
            'last_error' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
