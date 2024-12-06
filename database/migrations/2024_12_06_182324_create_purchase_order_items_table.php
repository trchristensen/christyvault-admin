<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up()
{
    Schema::create('purchase_order_items', function (Blueprint $table) {
        $table->id();
        $table->foreignId('purchase_order_id')->constrained()->onDelete('cascade');
        $table->foreignId('inventory_item_id')->constrained();
        $table->foreignId('supplier_id')->constrained();
        $table->string('supplier_sku')->nullable();
        $table->decimal('quantity', 10, 2);
        $table->decimal('unit_price', 10, 2);
        $table->decimal('total_price', 10, 2);
        $table->decimal('received_quantity', 10, 2)->default(0);
        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
