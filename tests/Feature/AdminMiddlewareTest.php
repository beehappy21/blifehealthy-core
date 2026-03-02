<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_is_blocked_but_admin_can_access(): void
    {
        $user = User::create([
            'member_code' => 'TH0000200',
            'name' => 'User',
            'phone' => '0833333333',
            'password_hash' => bcrypt('secret123'),
            'status' => 'active',
            'referrer_member_code' => 'TH0000001',
            'ref_status' => 'CONFIRMED',
        ]);

        $admin = User::create([
            'member_code' => 'TH0000201',
            'name' => 'Admin',
            'phone' => '0844444444',
            'password_hash' => bcrypt('secret123'),
            'status' => 'active',
            'referrer_member_code' => 'TH0000001',
            'ref_status' => 'CONFIRMED',
        ]);

        $adminRoleId = DB::table('roles')->insertGetId([
            'name' => 'admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('user_roles')->insert([
            'user_id' => $admin->id,
            'role_id' => $adminRoleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($user);
        $this->getJson('/api/admin/orders')->assertStatus(403);

        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/orders')->assertOk();
    }
}
