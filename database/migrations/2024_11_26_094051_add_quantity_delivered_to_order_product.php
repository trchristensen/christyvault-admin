<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('order_product', function (Blueprint $table) {
            $table->integer('quantity_delivered')->nullable();
            $table->text('delivery_notes')->nullable();
        });
    }

    public function down()
    {
        Schema::table('order_product', function (Blueprint $table) {
            $table->dropColumn(['quantity_delivered', 'delivery_notes']);
        });
    }
};
