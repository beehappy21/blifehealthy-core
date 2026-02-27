<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('coupon_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->string('type', 80);     // scanned, scan_requested_confirm, confirmed, redeemed
            $table->text('payload')->nullable(); // json string
            $table->timestamps();

            $table->index(['coupon_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_events');
    }
};