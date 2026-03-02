<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(InitialSetupSeeder::class);

        User::firstOrCreate(
            ['phone' => '0900000000'],
            [
                'member_code' => 'TH0000002',
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password_hash' => Hash::make('123456'),
                'status' => 'active',
                'referrer_member_code' => 'TH0000001',
                'ref_status' => 'CONFIRMED',
            ]
        );
    }
}
