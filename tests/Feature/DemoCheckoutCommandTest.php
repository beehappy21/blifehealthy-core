<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoCheckoutCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_checkout_is_idempotent(): void
    {
        Artisan::call('db:seed');

        $this->artisan('demo:checkout')->assertExitCode(0);
        $this->artisan('demo:checkout')->assertExitCode(0);

        $this->assertSame(1, DB::table('users')->where('member_code', 'TH0000001')->count());
        $this->assertSame(1, DB::table('users')->where('member_code', 'TH0000002')->count());
        $this->assertSame(1, DB::table('merchants')->count());
        $this->assertSame(1, DB::table('products')->count());
        $this->assertSame(1, DB::table('product_variants')->count());
        $this->assertGreaterThanOrEqual(1, DB::table('user_addresses')->where('user_id', DB::table('users')->where('member_code', 'TH0000001')->value('id'))->count());
    }
}
