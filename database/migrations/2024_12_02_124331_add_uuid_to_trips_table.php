<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // First add the column as nullable
        Schema::table('trips', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique();
        });

        // Generate UUIDs for existing records
        DB::table('trips')
            ->whereNull('uuid')
            ->orderBy('id')
            ->each(function ($trip) {
                DB::table('trips')
                    ->where('id', $trip->id)
                    ->update(['uuid' => Str::uuid()]);
            });

        // Now make it not nullable
        Schema::table('trips', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
};
