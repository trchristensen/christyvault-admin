<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_configurations', function (Blueprint $table): void {
            $table->unsignedTinyInteger('flatbed_pallet_capacity')
                ->default(0)
                ->after('rack_spot_count');
        });

        DB::table('vehicle_configurations')
            ->where('configuration_type', 'rack_trailer')
            ->where('rack_spot_count', 8)
            ->update([
                'flatbed_pallet_capacity' => 4,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('vehicle_configurations', function (Blueprint $table): void {
            $table->dropColumn('flatbed_pallet_capacity');
        });
    }
};
