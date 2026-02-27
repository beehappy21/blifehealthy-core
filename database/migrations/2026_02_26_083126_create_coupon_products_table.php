<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('coupon_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('products')->cascadeOnDelete();
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();

            // รูปแบบสิทธิ์คูปอง (ส่วนลดจากราคาขาย)
            $table->enum('discount_type', ['percent','fixed'])->default('fixed');
            $table->decimal('discount_value', 12, 2)->default(0); // เช่น 10% หรือ 50 บาท

            // อายุคูปอง
            $table->unsignedInteger('expiry_days')->default(30);

            // ต้องให้ผู้ซื้อยืนยันก่อนใช้ (ตามที่คุณเลือก “ยืนยันก่อน”)
            $table->boolean('require_confirm')->default(true);

            $table->text('terms')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_products');
    }
};