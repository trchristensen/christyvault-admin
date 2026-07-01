<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('calendar_days', 'plant_location')) {
            return;
        }

        Schema::table('calendar_days', function (Blueprint $table) {
            $table->dropColumn('plant_location');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('calendar_days', 'plant_location')) {
            return;
        }

        Schema::table('calendar_days', function (Blueprint $table) {
            $table->string('plant_location')->nullable()->after('opens_delivery');
            $table->index(['date', 'plant_location']);
        });
    }
};
