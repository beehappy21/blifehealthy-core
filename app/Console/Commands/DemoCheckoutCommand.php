<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\MemberCodeGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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

    public function handle(MemberCodeGenerator $memberCodeGenerator): int
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

        $result = DB::transaction(function () use ($memberCodeGenerator, $price) {
            $customer = $this->upsertUser(
                (string)$this->option('customer-email'),
                (string)$this->option('customer-password'),
                'Demo Customer',
                '08',
                $memberCodeGenerator
            );
            $customerToken = $customer->createToken('demo-checkout-customer')->plainTextToken;

            $addressId = $this->ensureDefaultAddress($customer, 'Home');

            $merchantOwner = $this->upsertUser(
                (string)$this->option('merchant-owner-email'),
                (string)$this->option('merchant-owner-password'),
                'Demo Merchant Owner',
                '09',
                $memberCodeGenerator
            );

            $merchantId = $this->ensureMerchant($merchantOwner, (string)$this->option('merchant-name'));
            $productId  = $this->ensureProduct($merchantId, (string)$this->option('product-name'));
            $variantId  = $this->ensureVariant($productId, $price);

            return compact('customer','customerToken','merchantOwner','merchantId','productId','variantId','addressId');
        });

        $this->info('✅ Demo checkout data created');
        $this->line('Customer ID: '.$result['customer']->id);
        $this->line('Customer member_code: '.$result['customer']->member_code);
        $this->line('Customer token: '.$result['customerToken']);
        $this->line('Merchant owner ID: '.$result['merchantOwner']->id);
        $this->line('Merchant ID: '.$result['merchantId']);
        $this->line('Product ID: '.$result['productId']);
        $this->line('Variant ID: '.$result['variantId']);
        $this->line('Default address ID: '.$result['addressId']);

        $this->line('WAP: /shop/settings.html , /shop/index.html');
        $this->line('API: /api/shop/{merchantId}/products');

        return self::SUCCESS;
    }

    private function filter(string $table, array $data): array
    {
        $cols = Schema::getColumnListing($table);
        return array_intersect_key($data, array_flip($cols));
    }

    private function upsertUser(string $email, string $password, string $namePrefix, string $phonePrefix, MemberCodeGenerator $gen): User
    {
        $existing = User::where('email', $email)->first();

        $attrs = [
            'name' => $namePrefix,
            'password_hash' => Hash::make($password),
            'password' => Hash::make($password),
            'status' => 'active',
            'ref_input_code' => null,
            'referrer_member_code' => env('DEFAULT_SPONSOR_CODE', 'TH0000001'),
            'ref_status' => 'DEFAULTED',
            'ref_source' => 'manual',
            'ref_locked_at' => now(),
            'updated_at' => now(),
        ];

        if ($existing) {
            if (empty($existing->phone)) {
                $attrs['phone'] = $this->generateUniquePhone($phonePrefix);
            }
            if (empty($existing->email) && $email !== '') {
                $attrs['email'] = $email;
            }

            $existing->forceFill($this->filter('users', $attrs))->save();

            return $existing;
        }

        $memberCode = $gen->generate();
        while (User::where('member_code', $memberCode)->exists()) {
            $memberCode = $gen->generate();
        }

        $user = User::updateOrCreate(
            ['member_code' => $memberCode],
            $this->filter('users', array_merge($attrs, [
                'member_code' => $memberCode,
                'phone' => $this->generateUniquePhone($phonePrefix),
                'email' => $email,
                'created_at' => now(),
            ]))
        );

        return $user;
    }

    private function generateUniquePhone(string $prefix): string
    {
        do {
            $phone = $prefix.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        } while (User::where('phone', $phone)->exists());

        return $phone;
    }

    private function ensureDefaultAddress(User $customer, string $label): int
    {
        if (!Schema::hasTable('user_addresses')) {
            throw new \RuntimeException('Missing table user_addresses');
        }

        // unset existing default if column exists
        if (Schema::hasColumn('user_addresses', 'is_default')) {
            DB::table('user_addresses')->where('user_id', $customer->id)->update(['is_default' => false]);
        }

        $data = $this->filter('user_addresses', [
            'user_id' => $customer->id,
            'label' => $label,
            'name' => $customer->name,
            'receiver_name' => $customer->name,
            'recipient_name' => $customer->name,
            'phone' => $customer->phone,
            'receiver_phone' => $customer->phone,
            'address' => '123 Demo Street',
            'address_line1' => '123 Demo Street',
            'address_line2' => null,
            'subdistrict' => 'Demo Subdistrict',
            'district' => 'Demo District',
            'province' => 'Bangkok',
            'postcode' => '10110',
            'postal_code' => '10110',
            'country' => 'TH',
            'lat' => null,
            'lng' => null,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('user_addresses')->insertGetId($data);
    }

    private function ensureMerchant(User $owner, string $merchantName): int
    {
        if (!Schema::hasTable('merchants')) {
            throw new \RuntimeException('Missing table merchants');
        }

        $data = $this->filter('merchants', [
            'owner_user_id' => $owner->id,
            'user_id' => $owner->id,
            'shop_name' => $merchantName,
            'name' => $merchantName,
            'display_name' => $merchantName,
            'slug' => 'demo-shop-'.Str::lower(Str::random(6)),
            'phone' => $owner->phone,
            'email' => $owner->email,
            'status' => 'approved',
            'admin_note' => 'Generated by demo:checkout',
            'approved_at' => now(),
            'rejected_at' => null,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('merchants')->insertGetId($data);
    }

    private function ensureProduct(int $merchantId, string $productName): int
    {
        if (!Schema::hasTable('products')) {
            throw new \RuntimeException('Missing table products');
        }

        $data = $this->filter('products', [
            'merchant_id' => $merchantId,
            'category_id' => null,
            'name' => $productName,
            'title' => $productName,
            'description' => 'Demo product created by demo:checkout.',
            'status' => 'active',
            'is_active' => 1,
            'is_published' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('products')->insertGetId($data);
    }

    private function ensureVariant(int $productId, float $price): int
    {
        if (!Schema::hasTable('product_variants')) {
            throw new \RuntimeException('Missing table product_variants');
        }

        $data = $this->filter('product_variants', [
            'product_id' => $productId,
            'sku' => 'DEMO-'.Str::upper(Str::random(10)),
            'option_json' => json_encode(['pack_qty' => 1], JSON_UNESCAPED_UNICODE),
            'price' => $price,
            'weight_g' => 300,
            'stock_qty' => 100,
            'stock' => 100,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('product_variants')->insertGetId($data);
    }
}
