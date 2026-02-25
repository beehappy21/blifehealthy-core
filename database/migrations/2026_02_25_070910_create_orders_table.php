<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->string('order_no', 30)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();

            $table->enum('status', [
                'WAITING_PAYMENT',
                'PAYMENT_REVIEW',
                'PAYMENT_REJECTED',
                'PAID',
                'SHIPPING_CREATED',
                'SHIPPED',
                'CANCELLED',
            ])->default('WAITING_PAYMENT')->index();

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('shipping_fee', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->string('shipping_provider', 50)->nullable();
            $table->json('shipping_option_json')->nullable();

            $table->timestamp('paid_at')->nullable()->index();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('merchant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};