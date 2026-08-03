<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_assets', function (Blueprint $table): void {
            $table->string('license_plate')->nullable()->after('serial_number');
            $table->date('registration_expires_on')->nullable()->after('warranty_expires_on');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_assets', function (Blueprint $table): void {
            $table->dropColumn(['license_plate', 'registration_expires_on']);
        });
    }
};
