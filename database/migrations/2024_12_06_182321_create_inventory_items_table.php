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
    Schema::create('inventory_items', function (Blueprint $table) {
        $table->id();
        $table->string('sku')->unique();
        $table->string('name');
        $table->text('description')->nullable();
        $table->string('category');
        $table->string('unit_of_measure');
        $table->decimal('minimum_stock', 10, 2);
        $table->decimal('current_stock', 10, 2)->default(0);
        $table->integer('reorder_lead_time')->nullable(); // days
        $table->string('storage_location')->nullable();
        $table->string('qr_code')->unique()->nullable();
        $table->boolean('active')->default(true);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
