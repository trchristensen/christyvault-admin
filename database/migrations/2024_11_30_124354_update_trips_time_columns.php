<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // First, create temporary columns
        Schema::table('trips', function (Blueprint $table) {
            $table->dateTime('start_time_new')->nullable();
            $table->dateTime('end_time_new')->nullable();
        });

        // Migrate existing data
        DB::table('trips')
            ->whereNotNull('start_time')
            ->orderBy('id')
            ->chunk(100, function ($trips) {
                foreach ($trips as $trip) {
                    DB::table('trips')
                        ->where('id', $trip->id)
                        ->update([
                            'start_time_new' => $trip->start_time ? date('Y-m-d H:i:s', strtotime($trip->start_time)) : null,
                            'end_time_new' => $trip->end_time ? date('Y-m-d H:i:s', strtotime($trip->end_time)) : null,
                        ]);
                }
            });

        // Drop old columns and rename new ones
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn(['start_time', 'end_time']);
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->renameColumn('start_time_new', 'start_time');
            $table->renameColumn('end_time_new', 'end_time');
        });
    }

    public function down()
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->string('start_time')->nullable()->change();
            $table->string('end_time')->nullable()->change();
        });
    }
};
