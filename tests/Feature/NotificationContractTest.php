<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_event_notifications_contract_and_sorting(): void
    {
        Storage::fake('public');

        $owner = User::create([
            'member_code' => 'TH0000500', 'name' => 'Owner', 'phone' => '0891000000', 'password_hash' => bcrypt('secret123'),
            'status' => 'active', 'referrer_member_code' => 'TH0000001', 'ref_status' => 'CONFIRMED',
        ]);
        $buyer = User::create([
            'member_code' => 'TH0000501', 'name' => 'Buyer', 'phone' => '0891000001', 'password_hash' => bcrypt('secret123'),
            'status' => 'active', 'referrer_member_code' => 'TH0000001', 'ref_status' => 'CONFIRMED',
        ]);

        $merchantId = DB::table('merchants')->insertGetId([
            'owner_user_id' => $owner->id,
            'shop_name' => 'Merchant N',
            'phone' => '0891000000',
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $productId = DB::table('products')->insertGetId([
            'merchant_id' => $merchantId,
            'name' => 'P1',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $variantId = DB::table('product_variants')->insertGetId([
            'product_id' => $productId,
            'sku' => 'SKU-NOTI-001',
            'option_json' => json_encode(['size' => 'M']),
            'price' => 100,
            'weight_g' => 100,
            'stock_qty' => 10,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $addressId = DB::table('user_addresses')->insertGetId([
            'user_id' => $buyer->id,
            'receiver_name' => 'Buyer',
            'receiver_phone' => '0891000001',
            'address_line1' => 'A',
            'country' => 'TH',
            'is_default' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($buyer);
        $orderRes = $this->postJson('/api/orders', [
            'address_id' => $addressId,
            'items' => [[
                'product_id' => $productId,
                'variant_id' => $variantId,
                'qty' => 1,
            ]],
        ])->assertStatus(201)->json('item');

        $orderId = $orderRes['id'];

        $this->post("/api/orders/{$orderId}/payment-slip", [
            'slip' => UploadedFile::fake()->image('slip.jpg'),
            'amount' => 100,
        ])->assertOk();

        $slipId = DB::table('payment_slips')->where('order_id', $orderId)->value('id');

        Sanctum::actingAs($owner);
        $this->postJson("/api/merchant/payment-slips/{$slipId}/approve", [])->assertOk();
        $this->patchJson("/api/merchant/orders/{$orderId}/shipment", [
            'provider' => 'THPOST',
            'tracking_no' => 'NTRK001',
        ])->assertOk();
        $this->patchJson("/api/merchant/orders/{$orderId}/mark-shipped", [])->assertOk();

        Sanctum::actingAs($buyer);
        $resp = $this->getJson('/api/me/notifications?limit=50')->assertOk();

        $items = $resp->json('items');
        $types = array_column($items, 'type');

        $this->assertContains('order.created', $types);
        $this->assertContains('slip.submitted', $types);
        $this->assertContains('slip.approved', $types);
        $this->assertContains('order.shipped', $types);

        foreach ($items as $item) {
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('source', $item);
            $this->assertArrayHasKey('type', $item);
            $this->assertArrayHasKey('title', $item);
            $this->assertArrayHasKey('message', $item);
            $this->assertArrayHasKey('payload', $item);
            $this->assertArrayHasKey('created_at', $item);
        }

        $created = array_column($items, 'created_at');
        $sorted = $created;
        rsort($sorted);
        $this->assertSame($sorted, $created);
    }
}
