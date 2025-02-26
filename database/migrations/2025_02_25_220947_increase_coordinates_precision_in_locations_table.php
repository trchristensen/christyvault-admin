<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->decimal('latitude', 14, 12)->nullable()->change();
            $table->decimal('longitude', 15, 12)->nullable()->change();
            // 14,12 for latitude allows range from -90 to 90 with 12 decimal places
            // 15,12 for longitude allows range from -180 to 180 with 12 decimal places
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->decimal('latitude', 12, 10)->nullable()->change();
            $table->decimal('longitude', 13, 10)->nullable()->change();
        });
    }
};
