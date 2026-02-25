<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

            $table->string('sku', 80)->unique();
            $table->json('option_json'); // {color,size,pack_qty}
            $table->decimal('price', 12, 2);

            $table->unsignedInteger('weight_g')->default(0);
            $table->integer('stock_qty')->default(0);

            $table->enum('status', ['active', 'inactive'])->default('active')->index();

            $table->timestamps();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};