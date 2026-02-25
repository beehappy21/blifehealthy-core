<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('merchant_kyc', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete()->unique();

            $table->json('docs_json'); // if JSON not supported -> longText
            $table->timestamp('submitted_at')->nullable();

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('review_note', 255)->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->index('reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_kyc');
    }
};