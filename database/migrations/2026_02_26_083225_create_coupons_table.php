<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();

            $table->string('code', 64)->unique(); // ใช้ทำ QR
            $table->foreignId('coupon_product_id')->constrained('coupon_products')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();

            $table->foreignId('buyer_user_id')->constrained('users')->cascadeOnDelete();

            $table->enum('status', ['issued','pending_confirm','confirmed','redeemed','void'])
                ->default('pending_confirm');

            $table->dateTime('issued_at')->nullable();
            $table->dateTime('expires_at')->nullable();

            $table->dateTime('confirmed_at')->nullable();

            $table->dateTime('redeemed_at')->nullable();
            $table->foreignId('redeemed_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->index(['merchant_id','status']);
            $table->index(['buyer_user_id','status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};