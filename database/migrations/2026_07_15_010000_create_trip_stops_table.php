<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_stops', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trip_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('sequence')->nullable();
            $table->text('delivery_notes')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();

            $table->index(['trip_id', 'removed_at']);
            $table->index(['order_id', 'removed_at']);
        });

        // Partial indexes preserve historical/removed stops while enforcing uniqueness
        // for the active route. PostgreSQL and SQLite both support this syntax.
        DB::statement('CREATE UNIQUE INDEX trip_stops_active_sequence_unique ON trip_stops (trip_id, sequence) WHERE removed_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX trip_stops_active_order_unique ON trip_stops (trip_id, order_id) WHERE removed_at IS NULL AND order_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_stops');
    }
};
