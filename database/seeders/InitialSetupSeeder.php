<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class InitialSetupSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // 1) Roles
            $roles = ['customer', 'merchant', 'admin'];
            foreach ($roles as $role) {
                DB::table('roles')->updateOrInsert(
                    ['name' => $role],
                    ['updated_at' => now(), 'created_at' => now()]
                );
            }

            // 2) Sequences
            DB::table('sequences')->updateOrInsert(
                ['key' => 'member_code_th'],
                ['current_value' => 1, 'updated_at' => now(), 'created_at' => now()] // start at 1 => TH0000001
            );

            DB::table('sequences')->updateOrInsert(
                ['key' => 'order_no_daily'],
                ['current_value' => 0, 'updated_at' => now(), 'created_at' => now()]
            );

            // 3) Default Sponsor (TH0000001)
            // password_hash: ใช้ Hash::make แต่เราตั้งชื่อคอลัมน์ password_hash
            $sponsorExists = DB::table('users')->where('member_code', 'TH0000001')->exists();

            if (!$sponsorExists) {
                DB::table('users')->insert([
                    'member_code' => 'TH0000001',
                    'name' => 'BLife Sponsor',
                    'phone' => '0000000000',
                    'email' => null,
                    'password_hash' => Hash::make('ChangeMe123!'),

                    'status' => 'active',

                    'ref_input_code' => null,
                    'referrer_member_code' => 'TH0000001',
                    'ref_status' => 'CONFIRMED',
                    'ref_source' => null,
                    'ref_locked_at' => now(),

                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // assign admin role to sponsor user (optional but useful)
                $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
                $userId = DB::table('users')->where('member_code', 'TH0000001')->value('id');

                if ($adminRoleId && $userId) {
                    DB::table('user_roles')->updateOrInsert(
                        ['user_id' => $userId, 'role_id' => $adminRoleId],
                        ['created_at' => now(), 'updated_at' => now()]
                    );
                }
            }
        });
    }
}