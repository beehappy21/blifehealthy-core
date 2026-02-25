<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('integration_outbox', function (Blueprint $table) {
            $table->id();

            $table->string('event_type', 50)->index(); // member.created, order.paid
            $table->json('payload_json');

            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending')->index();
            $table->unsignedInteger('retry_count')->default(0);

            $table->timestamp('next_retry_at')->nullable()->index();
            $table->string('last_error', 500)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_outbox');
    }
};