<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'member_code',
        'name',
        'phone',
        'email',
        'password_hash',
        'status',
        'ref_input_code',
        'referrer_member_code',
        'ref_status',
        'ref_source',
        'ref_locked_at',
    ];

    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    protected $casts = [
        'ref_locked_at' => 'datetime',
    ];
}