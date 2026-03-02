<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderShippingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_set_tracking_updates_status_to_shipping_created(): void
    {
        [$owner, $orderId] = $this->seedOrder('PAID');
        Sanctum::actingAs($owner);

        $this->patchJson("/api/merchant/orders/{$orderId}/shipment", [
            'provider' => 'THPOST',
            'tracking_no' => 'TRK001',
            'fee' => 50,
        ])->assertOk();

        $this->assertSame('SHIPPING_CREATED', DB::table('orders')->where('id', $orderId)->value('status'));
        $this->assertSame('TRK001', DB::table('shipments')->where('order_id', $orderId)->value('tracking_no'));
    }

    public function test_merchant_mark_shipped_updates_status_to_shipped(): void
    {
        [$owner, $orderId] = $this->seedOrder('PAID');
        Sanctum::actingAs($owner);
        $this->patchJson("/api/merchant/orders/{$orderId}/shipment", [
            'provider' => 'THPOST',
            'tracking_no' => 'TRK002',
        ])->assertOk();

        $this->patchJson("/api/merchant/orders/{$orderId}/mark-shipped", [])
            ->assertOk();

        $this->assertSame('SHIPPED', DB::table('orders')->where('id', $orderId)->value('status'));
    }

    public function test_admin_can_ship_and_non_admin_blocked(): void
    {
        [$owner, $orderId] = $this->seedOrder('PAID');
        $nonAdmin = User::create([
            'member_code' => 'TH0000401',
            'name' => 'Non Admin',
            'phone' => '0881000001',
            'password_hash' => bcrypt('secret123'),
            'status' => 'active',
            'referrer_member_code' => 'TH0000001',
            'ref_status' => 'CONFIRMED',
        ]);

        $admin = User::create([
            'member_code' => 'TH0000402',
            'name' => 'Admin',
            'phone' => '0881000002',
            'password_hash' => bcrypt('secret123'),
            'status' => 'active',
            'referrer_member_code' => 'TH0000001',
            'ref_status' => 'CONFIRMED',
        ]);
        $adminRoleId = DB::table('roles')->insertGetId(['name' => 'admin', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('user_roles')->insert(['user_id' => $admin->id, 'role_id' => $adminRoleId, 'created_at' => now(), 'updated_at' => now()]);

        Sanctum::actingAs($nonAdmin);
        $this->patchJson("/api/admin/orders/{$orderId}/shipment", [
            'provider' => 'THPOST',
            'tracking_no' => 'TRK003',
        ])->assertStatus(403)->assertJson(['code' => 'FORBIDDEN_RESOURCE']);

        Sanctum::actingAs($admin);
        $this->patchJson("/api/admin/orders/{$orderId}/shipment", [
            'provider' => 'THPOST',
            'tracking_no' => 'TRK003',
        ])->assertOk();
        $this->patchJson("/api/admin/orders/{$orderId}/mark-shipped", [])->assertOk();
        $this->assertSame('SHIPPED', DB::table('orders')->where('id', $orderId)->value('status'));
    }


    public function test_set_tracking_twice_keeps_single_shipment_row(): void
    {
        [$owner, $orderId] = $this->seedOrder('PAID');
        Sanctum::actingAs($owner);

        $this->patchJson("/api/merchant/orders/{$orderId}/shipment", [
            'provider' => 'THPOST',
            'tracking_no' => 'TRK101',
            'fee' => 10,
        ])->assertOk();

        $this->patchJson("/api/merchant/orders/{$orderId}/shipment", [
            'provider' => 'KERRY',
            'tracking_no' => 'TRK102',
            'fee' => 20,
        ])->assertOk();

        $this->assertSame(1, DB::table('shipments')->where('order_id', $orderId)->count());
        $this->assertSame('KERRY', DB::table('shipments')->where('order_id', $orderId)->value('provider'));
        $this->assertSame('TRK102', DB::table('shipments')->where('order_id', $orderId)->value('tracking_no'));
    }

    public function test_mark_shipped_before_tracking_returns_conflict(): void
    {
        [$owner, $orderId] = $this->seedOrder('PAID');
        Sanctum::actingAs($owner);

        $this->patchJson("/api/merchant/orders/{$orderId}/mark-shipped", [])
            ->assertStatus(409)
            ->assertJson(['code' => 'ORDER_STATE_CONFLICT']);
    }

    private function seedOrder(string $status): array
    {
        $owner = User::create([
            'member_code' => 'TH0000400',
            'name' => 'Merchant Owner',
            'phone' => '0881000000',
            'password_hash' => bcrypt('secret123'),
            'status' => 'active',
            'referrer_member_code' => 'TH0000001',
            'ref_status' => 'CONFIRMED',
        ]);
        $buyer = User::create([
            'member_code' => 'TH0000410',
            'name' => 'Buyer',
            'phone' => '0882000000',
            'password_hash' => bcrypt('secret123'),
            'status' => 'active',
            'referrer_member_code' => 'TH0000001',
            'ref_status' => 'CONFIRMED',
        ]);

        $merchantId = DB::table('merchants')->insertGetId([
            'owner_user_id' => $owner->id,
            'shop_name' => 'Merchant A',
            'phone' => '0881000000',
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('orders')->insertGetId([
            'order_no' => 'ORDSHIP001',
            'user_id' => $buyer->id,
            'merchant_id' => $merchantId,
            'status' => $status,
            'subtotal' => 100,
            'shipping_fee' => 0,
            'total' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$owner, $orderId];
    }
}
