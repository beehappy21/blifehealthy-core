<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // รายละเอียดแบบยาว
            $table->longText('detail')->nullable()->after('description');

            // วิดีโอสินค้า (เก็บเป็น URL ก่อน เช่น YouTube/Cloud storage)
            $table->string('video_url', 500)->nullable()->after('detail');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['detail','video_url']);
        });
    }
};