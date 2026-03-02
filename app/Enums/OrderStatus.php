<?php

namespace App\Enums;

final class OrderStatus
{
    public const WAITING_PAYMENT = 'WAITING_PAYMENT';
    public const PAYMENT_REVIEW = 'PAYMENT_REVIEW';
    public const PAYMENT_REJECTED = 'PAYMENT_REJECTED';
    public const PAID = 'PAID';
    public const SHIPPING_CREATED = 'SHIPPING_CREATED';
    public const SHIPPED = 'SHIPPED';
    public const CANCELLED = 'CANCELLED';

    public static function values(): array
    {
        return [
            self::WAITING_PAYMENT,
            self::PAYMENT_REVIEW,
            self::PAYMENT_REJECTED,
            self::PAID,
            self::SHIPPING_CREATED,
            self::SHIPPED,
            self::CANCELLED,
        ];
    }

    public static function normalize(string $status): string
    {
        return strtoupper(trim($status));
    }
}
