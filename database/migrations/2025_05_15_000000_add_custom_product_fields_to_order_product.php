<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_product', function (Blueprint $table) {
            $table->text('custom_description')->nullable()->after('custom_name');
            $table->boolean('is_custom_product')->default(false)->after('custom_description');
        });
    }

    public function down(): void
    {
        Schema::table('order_product', function (Blueprint $table) {
            $table->dropColumn([
                'custom_description',
                'is_custom_product'
            ]);
        });
    }
}; 