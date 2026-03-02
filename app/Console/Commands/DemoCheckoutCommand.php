<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DemoCheckoutCommand extends Command
{
    protected $signature = 'demo:checkout
        {--customer-email=customer.demo@example.com}
        {--customer-password=secret123}
        {--merchant-owner-email=merchant.owner.demo@example.com}
        {--merchant-owner-password=secret123}
        {--merchant-name=Demo Merchant Shop}
        {--product-name=Demo Product}
        {--price=199.00}';

    protected $description = 'Seed demo checkout-ready data (customer+token, merchant+product+variant, default address).';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('demo:checkout is disabled in production.');
            return self::FAILURE;
        }

        $price = (float) $this->option('price');
        if ($price < 0) {
            $this->error('The --price option must be >= 0.');
            return self::FAILURE;
        }

        $result = DB::transaction(function () use ($price) {
            $customer = $this->upsertUser('TH0000001', (string) $this->option('customer-email'), (string) $this->option('customer-password'), 'Demo Customer', '0800000001');
            $customerToken = $customer->createToken('demo-checkout-customer')->plainTextToken;

            $merchantOwner = $this->upsertUser('TH0000002', (string) $this->option('merchant-owner-email'), (string) $this->option('merchant-owner-password'), 'Demo Merchant Owner', '0900000001');
            $addressId = $this->ensureDefaultAddress($customer, 'Home');
            $merchantId = $this->ensureMerchant($merchantOwner, (string) $this->option('merchant-name'));
            $productId = $this->ensureProduct($merchantId, (string) $this->option('product-name'));
            $variantId = $this->ensureVariant($productId, $price);

            return compact('customer', 'customerToken', 'merchantOwner', 'merchantId', 'productId', 'variantId', 'addressId');
        });

        $this->info('✅ Demo checkout data created');
        $this->line('Customer ID: ' . $result['customer']->id);
        $this->line('Customer member_code: ' . $result['customer']->member_code);
        $this->line('Customer token: ' . $result['customerToken']);
        $this->line('Merchant owner ID: ' . $result['merchantOwner']->id);
        $this->line('Merchant ID: ' . $result['merchantId']);
        $this->line('Product ID: ' . $result['productId']);
        $this->line('Variant ID: ' . $result['variantId']);
        $this->line('Default address ID: ' . $result['addressId']);
        $this->line('WAP: /shop/settings.html , /shop/index.html');
        $this->line('API: /api/shop/{merchantId}/products');

        return self::SUCCESS;
    }

    private function filter(string $table, array $data): array
    {
        $cols = Schema::getColumnListing($table);
        return array_intersect_key($data, array_flip($cols));
    }

    private function upsertUser(string $memberCode, string $email, string $password, string $name, string $phone): User
    {
        $payload = $this->filter('users', [
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'password_hash' => Hash::make($password),
            'password' => Hash::make($password),
            'status' => 'active',
            'ref_input_code' => null,
            'referrer_member_code' => 'TH0000001',
            'ref_status' => 'DEFAULTED',
            'ref_source' => 'manual',
            'ref_locked_at' => now(),
            'updated_at' => now(),
            'created_at' => now(),
        ]);

        User::updateOrCreate(['member_code' => $memberCode], $payload);

        return User::where('member_code', $memberCode)->firstOrFail();
    }

    private function ensureDefaultAddress(User $customer, string $label): int
    {
        if (Schema::hasColumn('user_addresses', 'is_default')) {
            DB::table('user_addresses')->where('user_id', $customer->id)->update(['is_default' => false, 'updated_at' => now()]);
        }

        $data = $this->filter('user_addresses', [
            'label' => $label,
            'receiver_name' => $customer->name,
            'receiver_phone' => $customer->phone,
            'address_line1' => '123 Demo Street',
            'address_line2' => null,
            'subdistrict' => 'Demo Subdistrict',
            'district' => 'Demo District',
            'province' => 'Bangkok',
            'postcode' => '10110',
            'country' => 'TH',
            'lat' => null,
            'lng' => null,
            'is_default' => true,
            'updated_at' => now(),
        ]);

        $exists = DB::table('user_addresses')->where('user_id', $customer->id)->where('label', $label)->first();
        if ($exists) {
            DB::table('user_addresses')->where('id', (int) $exists->id)->update($data);
            return (int) $exists->id;
        }

        $data['user_id'] = $customer->id;
        $data['created_at'] = now();
        return DB::table('user_addresses')->insertGetId($data);
    }

    private function ensureMerchant(User $owner, string $merchantName): int
    {
        $payload = $this->filter('merchants', [
            'shop_name' => $merchantName,
            'phone' => $owner->phone,
            'email' => $owner->email,
            'status' => 'approved',
            'admin_note' => 'Generated by demo:checkout',
            'approved_at' => now(),
            'updated_at' => now(),
            'created_at' => now(),
        ]);

        $exists = DB::table('merchants')->where('owner_user_id', $owner->id)->first();
        if ($exists) {
            DB::table('merchants')->where('id', (int) $exists->id)->update($payload);
            return (int) $exists->id;
        }

        $payload['owner_user_id'] = $owner->id;
        return DB::table('merchants')->insertGetId($payload);
    }

    private function ensureProduct(int $merchantId, string $productName): int
    {
        $payload = $this->filter('products', [
            'name' => $productName,
            'description' => 'Demo product created by demo:checkout.',
            'status' => 'active',
            'updated_at' => now(),
            'created_at' => now(),
        ]);

        $exists = DB::table('products')->where('merchant_id', $merchantId)->where('name', $productName)->first();
        if ($exists) {
            DB::table('products')->where('id', (int) $exists->id)->update($payload);
            return (int) $exists->id;
        }

        $payload['merchant_id'] = $merchantId;
        return DB::table('products')->insertGetId($payload);
    }

    private function ensureVariant(int $productId, float $price): int
    {
        $sku = 'DEMO-P' . str_pad((string) $productId, 8, '0', STR_PAD_LEFT);
        $payload = $this->filter('product_variants', [
            'product_id' => $productId,
            'sku' => $sku,
            'option_json' => json_encode(['pack_qty' => 1], JSON_UNESCAPED_UNICODE),
            'price' => $price,
            'weight_g' => 300,
            'stock_qty' => 100,
            'stock' => 100,
            'status' => 'active',
            'updated_at' => now(),
            'created_at' => now(),
        ]);

        $exists = DB::table('product_variants')->where('sku', $sku)->first();
        if ($exists) {
            DB::table('product_variants')->where('id', (int) $exists->id)->update($payload);
            return (int) $exists->id;
        }

        return DB::table('product_variants')->insertGetId($payload);
    }
}
