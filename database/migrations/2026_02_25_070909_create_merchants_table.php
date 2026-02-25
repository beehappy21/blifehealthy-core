<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();

            $table->string('shop_name', 200);
            $table->string('phone', 20);
            $table->string('email', 150)->nullable();

            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected'])
                ->default('submitted')
                ->index();

            $table->string('admin_note', 255)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();

            $table->timestamps();

            $table->index('owner_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchants');
    }
};