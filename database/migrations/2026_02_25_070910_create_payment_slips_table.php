<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_slips', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete()->unique();

            $table->string('image_url', 500);
            $table->decimal('amount', 12, 2);
            $table->timestamp('transfer_at')->nullable();

            $table->enum('status', ['submitted', 'approved', 'rejected'])->default('submitted')->index();

            $table->string('admin_note', 255)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete()->index();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_slips');
    }
};