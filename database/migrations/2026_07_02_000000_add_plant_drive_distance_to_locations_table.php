<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->foreignId('plant_drive_distance_origin_location_id')
                ->nullable()
                ->after('longitude')
                ->constrained('locations')
                ->nullOnDelete();
            $table->decimal('plant_drive_distance_miles', 8, 2)
                ->nullable()
                ->after('plant_drive_distance_origin_location_id');
            $table->unsignedInteger('plant_drive_duration_minutes')
                ->nullable()
                ->after('plant_drive_distance_miles');
            $table->string('plant_drive_distance_provider')
                ->nullable()
                ->after('plant_drive_duration_minutes');
            $table->timestamp('plant_drive_distance_calculated_at')
                ->nullable()
                ->after('plant_drive_distance_provider');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plant_drive_distance_origin_location_id');
            $table->dropColumn([
                'plant_drive_distance_miles',
                'plant_drive_duration_minutes',
                'plant_drive_distance_provider',
                'plant_drive_distance_calculated_at',
            ]);
        });
    }
};
