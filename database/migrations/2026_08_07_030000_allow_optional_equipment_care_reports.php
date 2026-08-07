<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_pre_trip_inspections', function (Blueprint $table): void {
            $table->foreignId('trip_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('trip_pre_trip_inspections', function (Blueprint $table): void {
            $table->foreignId('trip_id')->nullable(false)->change();
        });
    }
};
