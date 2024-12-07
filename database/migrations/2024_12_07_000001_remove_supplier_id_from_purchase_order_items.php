<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            // First drop the foreign key constraint
            $table->dropForeign(['supplier_id']);
            // Then drop the column
            $table->dropColumn('supplier_id');
        });
    }

    public function down()
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            // Add the column back
            $table->foreignId('supplier_id')->after('inventory_item_id')->constrained();
        });
    }
};
