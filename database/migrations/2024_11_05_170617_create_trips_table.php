<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // database/migrations/xxxx_create_trips_table.php
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->string('trip_number')->unique();  // TRIP-2024-00001
            $table->foreignId('driver_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->enum('status', [
                'pending',      // Trip created but not yet assigned
                'assigned',     // Driver assigned but not started
                'in_progress',  // Driver has started deliveries
                'completed',    // All deliveries completed
                'cancelled'     // Trip cancelled
            ])->default('pending');
            $table->date('scheduled_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
