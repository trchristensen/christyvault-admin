<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->timestamp('last_order_at')->nullable();
            $table->integer('average_order_frequency_days')->nullable();
            $table->json('common_order_items')->nullable();
            $table->integer('total_orders')->default(0);
            $table->decimal('average_order_value', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn([
                'last_order_at',
                'average_order_frequency_days',
                'common_order_items',
                'total_orders',
                'average_order_value',
            ]);
        });
    }
}; 