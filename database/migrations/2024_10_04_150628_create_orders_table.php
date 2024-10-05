<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('order_number')->unique(); // For human-readable reference
            $table->date('requested_delivery_date');
            $table->text('special_instructions')->nullable();
            $table->enum('status', [
                'pending',
                'confirmed',
                'in_production',
                'ready_for_delivery',
                'out_for_delivery',
                'delivered',
                'cancelled'
            ])->default('pending');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('orders');
    }
};
