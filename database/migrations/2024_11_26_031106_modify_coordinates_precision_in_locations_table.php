<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->decimal('latitude', 12, 10)->nullable()->change();
            $table->decimal('longitude', 13, 10)->nullable()->change();
            // 12,10 for latitude allows range from -90 to 90 with 10 decimal places
            // 13,10 for longitude allows range from -180 to 180 with 10 decimal places
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->decimal('latitude', 10, 8)->nullable()->change();
            $table->decimal('longitude', 11, 8)->nullable()->change();
        });
    }
};
