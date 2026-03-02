<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
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

    public function hasRole(string $name): bool
    {
        return DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $this->id)
            ->where('roles.name', $name)
            ->exists();
    }
}
