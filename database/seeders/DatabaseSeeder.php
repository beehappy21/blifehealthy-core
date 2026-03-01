<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::create([
            'member_code' => 'TH0000001',
            'name' => 'Test User',
            'phone' => '0900000000',
            'email' => 'test@example.com',
            'password_hash' => Hash::make('123456'),
            'status' => 'active',
            'referrer_member_code' => 'TH0000001',
            'ref_status' => 'CONFIRMED',
        ]);
    }
}
