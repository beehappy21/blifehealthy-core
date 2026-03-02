<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentSlipReviewFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_approve_and_reject_updates_order_status(): void
    {
        [$owner, $customer, $orderId, $slipId] = $this->seedReviewCase('PAYMENT_REVIEW');

        Sanctum::actingAs($owner);
        $this->postJson("/api/merchant/payment-slips/{$slipId}/approve")->assertOk();

        $this->assertSame('approved', DB::table('payment_slips')->where('id', $slipId)->value('status'));
        $this->assertSame('PAID', DB::table('orders')->where('id', $orderId)->value('status'));
        $this->assertNotNull(DB::table('orders')->where('id', $orderId)->value('paid_at'));

        DB::table('orders')->where('id', $orderId)->update(['status' => 'PAYMENT_REVIEW', 'paid_at' => null]);
        $this->postJson("/api/merchant/payment-slips/{$slipId}/reject", ['note' => 'invalid'])->assertOk();

        $this->assertSame('rejected', DB::table('payment_slips')->where('id', $slipId)->value('status'));
        $this->assertSame('PAYMENT_REJECTED', DB::table('orders')->where('id', $orderId)->value('status'));
    }

    public function test_merchant_cannot_review_other_merchant_slip(): void
    {
        [$ownerA, , , $slipId] = $this->seedReviewCase('PAYMENT_REVIEW');

        $ownerB = User::create([
            'member_code' => 'TH0000199',
            'name' => 'Other Merchant',
            'phone' => '0899999999',
            'password_hash' => bcrypt('secret123'),
            'status' => 'active',
            'referrer_member_code' => 'TH0000001',
            'ref_status' => 'CONFIRMED',
        ]);

        Sanctum::actingAs($ownerB);
        $this->postJson("/api/merchant/payment-slips/{$slipId}/approve")
            ->assertStatus(403);

        Sanctum::actingAs($ownerA);
        $this->postJson("/api/merchant/payment-slips/{$slipId}/approve")
            ->assertOk();
    }

    public function test_review_requires_order_in_payment_review_state(): void
    {
        [$owner, , , $slipId] = $this->seedReviewCase('PAID');

        Sanctum::actingAs($owner);
        $this->postJson("/api/merchant/payment-slips/{$slipId}/reject", ['note' => 'state test'])
            ->assertStatus(409);
    }

    public function test_upload_slip_for_cancelled_order_is_blocked(): void
    {
        Storage::fake('public');

        $customer = User::create([
            'member_code' => 'TH0000300',
            'name' => 'Customer',
            'phone' => '0866666666',
            'password_hash' => bcrypt('secret123'),
            'status' => 'active',
            'referrer_member_code' => 'TH0000001',
            'ref_status' => 'CONFIRMED',
        ]);

        $owner = User::create([
            'member_code' => 'TH0000301',
            'name' => 'Owner',
            'phone' => '0867777777',
            'password_hash' => bcrypt('secret123'),
            'status' => 'active',
            'referrer_member_code' => 'TH0000001',
            'ref_status' => 'CONFIRMED',
        ]);

        $merchantId = DB::table('merchants')->insertGetId([
            'owner_user_id' => $owner->id,
            'shop_name' => 'Merchant',
            'phone' => '0867777777',
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('orders')->insertGetId([
            'order_no' => 'ORDTESTCANCEL',
            'user_id' => $customer->id,
            'merchant_id' => $merchantId,
            'status' => 'CANCELLED',
            'subtotal' => 100,
            'shipping_fee' => 0,
            'total' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($customer);
        $this->post("/api/orders/{$orderId}/payment-slip", [
            'slip' => UploadedFile::fake()->image('slip.jpg'),
            'amount' => 100,
        ])->assertStatus(409);
    }

    private function seedReviewCase(string $orderStatus): array
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
            'status' => $orderStatus,
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

        return [$owner, $customer, $orderId, $slipId];
    }
}
