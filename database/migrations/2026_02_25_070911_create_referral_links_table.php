<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('referral_links', function (Blueprint $table) {
            $table->id();

            $table->string('new_member_code', 9);
            $table->string('ref_input_code', 9)->nullable();
            $table->string('referrer_member_code_final', 9);

            $table->enum('ref_status', ['CONFIRMED', 'DEFAULTED', 'PENDING']);
            $table->enum('ref_source', ['link', 'manual', 'qr'])->nullable();

            $table->string('note', 255)->nullable();

            $table->timestamps();

            $table->index('new_member_code');
            $table->index('referrer_member_code_final');
            $table->index('ref_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_links');
    }
};