<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class PosDevice extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'pos_devices';

    protected $fillable = [
        'merchant_id',
        'name',
        'device_uid',
        'created_by_user_id',
        'meta',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function isRevoked(): bool
    {
        return !is_null($this->revoked_at);
    }
}