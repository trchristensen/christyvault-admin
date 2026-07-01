<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('team_schedule_delivery_types')->nullable()->after('password');
            $table->unsignedSmallInteger('team_schedule_days_ahead')->nullable()->after('team_schedule_delivery_types');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'team_schedule_delivery_types',
                'team_schedule_days_ahead',
            ]);
        });
    }
};
