<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete()->unique();

            $table->string('provider', 50);
            $table->string('tracking_no', 100)->nullable()->index();
            $table->decimal('fee', 12, 2)->default(0);

            $table->string('status', 30)->nullable();
            $table->string('label_url', 500)->nullable();

            $table->json('payload_json')->nullable();

            $table->timestamps();

            $table->index('provider');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};