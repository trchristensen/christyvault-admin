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
    Schema::create('inventory_item_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
            $table->boolean('is_preferred')->default(false);
            $table->string('supplier_sku')->nullable();
            $table->decimal('cost_per_unit', 10, 2);
            $table->decimal('minimum_order_quantity', 10, 2)->default(1);
            $table->integer('lead_time_days')->nullable();
            $table->timestamp('last_supplied_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['inventory_item_id', 'supplier_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_item_suppliers');
    }
};
