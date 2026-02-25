<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            $table->string('member_code', 9)->unique(); // TH0000001
            $table->string('name', 150);
            $table->string('phone', 20)->index();
            $table->string('email', 150)->nullable();

            $table->string('password_hash', 255);

            $table->enum('status', ['active', 'suspended'])->default('active');

            $table->string('ref_input_code', 9)->nullable();
            $table->string('referrer_member_code', 9); // final sponsor code
            $table->enum('ref_status', ['CONFIRMED', 'DEFAULTED', 'PENDING'])->default('PENDING');
            $table->enum('ref_source', ['link', 'manual', 'qr'])->nullable();
            $table->timestamp('ref_locked_at')->nullable();

            $table->timestamps();

            $table->index('referrer_member_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};