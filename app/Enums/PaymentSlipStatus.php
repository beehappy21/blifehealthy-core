<?php

namespace App\Enums;

final class PaymentSlipStatus
{
    public const SUBMITTED = 'submitted';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';

    public static function values(): array
    {
        return [
            self::SUBMITTED,
            self::APPROVED,
            self::REJECTED,
        ];
    }

    public static function normalize(string $status): string
    {
        return strtolower(trim($status));
    }
}
