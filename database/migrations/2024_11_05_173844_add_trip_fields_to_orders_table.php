<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Add the trip relationship
            $table->foreignId('trip_id')
                ->nullable()
                ->after('id')  // You might want to adjust this position
                ->constrained()
                ->nullOnDelete();

            // Add delivery tracking fields
            $table->integer('stop_number')
                ->nullable()
                ->after('trip_id');

            $table->timestamp('delivered_at')
                ->nullable()
                ->after('stop_number');

            $table->string('signature_path')
                ->nullable()
                ->after('delivered_at');

            $table->text('delivery_notes')
                ->nullable()
                ->after('signature_path');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Remove the foreign key first
            $table->dropForeign(['trip_id']);

            // Then remove the columns
            $table->dropColumn([
                'trip_id',
                'stop_number',
                'delivered_at',
                'signature_path',
                'delivery_notes'
            ]);
        });
    }
};
