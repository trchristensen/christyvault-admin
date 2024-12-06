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
    Schema::create('purchase_orders', function (Blueprint $table) {
        $table->id();
        $table->foreignId('supplier_id')->constrained();
        $table->string('status'); // draft, submitted, received, cancelled
        $table->timestamp('order_date');
        $table->timestamp('expected_delivery_date')->nullable();
        $table->timestamp('received_date')->nullable();
        $table->decimal('total_amount', 10, 2);
        $table->text('notes')->nullable();
        $table->foreignId('created_by_user_id')->constrained('users');
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
