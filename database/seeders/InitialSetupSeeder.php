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
            foreach (['customer', 'merchant', 'admin'] as $role) {
                DB::table('roles')->updateOrInsert(
                    ['name' => $role],
                    ['updated_at' => now(), 'created_at' => now()]
                );
            }

            DB::table('sequences')->updateOrInsert(
                ['key' => 'member_code_th'],
                ['current_value' => 1, 'updated_at' => now(), 'created_at' => now()]
            );

            DB::table('sequences')->updateOrInsert(
                ['key' => 'order_no_daily'],
                ['current_value' => 0, 'updated_at' => now(), 'created_at' => now()]
            );

            $sponsorData = [
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
            ];

            DB::table('users')->updateOrInsert(['member_code' => 'TH0000001'], $sponsorData);

            $adminRoleId = (int) DB::table('roles')->where('name', 'admin')->value('id');
            if ($adminRoleId > 0) {
                $this->attachRoleByMemberCode('TH0000001', $adminRoleId);

                $adminMemberCode = trim((string) env('ADMIN_MEMBER_CODE', ''));
                if ($adminMemberCode !== '') {
                    $this->attachRoleByMemberCode($adminMemberCode, $adminRoleId);
                }
            }
        });
    }

    private function attachRoleByMemberCode(string $memberCode, int $roleId): void
    {
        $userId = DB::table('users')->where('member_code', $memberCode)->value('id');
        if (!$userId) {
            return;
        }

        DB::table('user_roles')->updateOrInsert(
            ['user_id' => (int) $userId, 'role_id' => $roleId],
            ['created_at' => now(), 'updated_at' => now()]
        );
    }
}
