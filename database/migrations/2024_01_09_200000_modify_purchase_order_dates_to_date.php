<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->date('order_date')->nullable()->change();
            $table->date('expected_delivery_date')->nullable()->change();
            $table->date('received_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->timestamp('order_date')->nullable()->change();
            $table->timestamp('expected_delivery_date')->nullable()->change();
            $table->timestamp('received_date')->nullable()->change();
        });
    }
};
