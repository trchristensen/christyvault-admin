<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // First alter the column type
        DB::statement("ALTER TABLE locations ALTER COLUMN location_type TYPE VARCHAR(255)");

        // Then add the check constraint
        DB::statement("ALTER TABLE locations ADD CONSTRAINT location_type_check CHECK (location_type IN ('funeral_home', 'cemetery', 'christy_vault', 'other'))");

        // Add the radius column
        Schema::table('locations', function (Blueprint $table) {
            $table->integer('radius_feet')->nullable();
        });
    }

    public function down(): void
    {
        // Remove the check constraint
        DB::statement("ALTER TABLE locations DROP CONSTRAINT location_type_check");

        // Remove the radius column
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('radius_feet');
        });
    }
};
