<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->enum('platform_fee_type', ['percent','fixed'])->nullable()->after('price');
            $table->decimal('platform_fee_value', 12, 2)->nullable()->after('platform_fee_type');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn(['platform_fee_type','platform_fee_value']);
        });
    }
};