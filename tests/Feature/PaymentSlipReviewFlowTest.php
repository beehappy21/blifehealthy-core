<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentSlipReviewFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_approve_and_reject_updates_order_status(): void
    {
        $owner = User::create([
            'member_code' => 'TH0000100',
            'name' => 'Merchant Owner',
            'phone' => '0811111111',
            'password_hash' => bcrypt('secret123'),
            'status' => 'active',
            'referrer_member_code' => 'TH0000001',
            'ref_status' => 'CONFIRMED',
        ]);

        $customer = User::create([
            'member_code' => 'TH0000101',
            'name' => 'Customer',
            'phone' => '0822222222',
            'password_hash' => bcrypt('secret123'),
            'status' => 'active',
            'referrer_member_code' => 'TH0000001',
            'ref_status' => 'CONFIRMED',
        ]);

        $merchantId = DB::table('merchants')->insertGetId([
            'owner_user_id' => $owner->id,
            'shop_name' => 'Merchant',
            'phone' => '0811111111',
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('orders')->insertGetId([
            'order_no' => 'ORDTEST001',
            'user_id' => $customer->id,
            'merchant_id' => $merchantId,
            'status' => 'PAYMENT_REVIEW',
            'subtotal' => 100,
            'shipping_fee' => 0,
            'total' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $slipId = DB::table('payment_slips')->insertGetId([
            'order_id' => $orderId,
            'image_url' => '/storage/slip.jpg',
            'amount' => 100,
            'status' => 'submitted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/merchant/payment-slips/{$slipId}/approve")->assertOk();

        $this->assertSame('approved', DB::table('payment_slips')->where('id', $slipId)->value('status'));
        $this->assertSame('PAID', DB::table('orders')->where('id', $orderId)->value('status'));
        $this->assertNotNull(DB::table('orders')->where('id', $orderId)->value('paid_at'));

        $this->postJson("/api/merchant/payment-slips/{$slipId}/reject", ['note' => 'invalid'])
            ->assertOk();

        $this->assertSame('rejected', DB::table('payment_slips')->where('id', $slipId)->value('status'));
        $this->assertSame('PAYMENT_REJECTED', DB::table('orders')->where('id', $orderId)->value('status'));
    }
}
