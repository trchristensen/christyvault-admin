<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('geocoding_provider')->nullable()->after('longitude');
            $table->string('geocoding_matched_address')->nullable()->after('geocoding_provider');
            $table->timestamp('geocoded_at')->nullable()->after('geocoding_matched_address');
            $table->timestamp('geocoding_failed_at')->nullable()->after('geocoded_at');
            $table->string('geocoding_failure_reason')->nullable()->after('geocoding_failed_at');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn([
                'geocoding_provider',
                'geocoding_matched_address',
                'geocoded_at',
                'geocoding_failed_at',
                'geocoding_failure_reason',
            ]);
        });
    }
};
