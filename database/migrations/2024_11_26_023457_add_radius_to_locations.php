<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->integer('radius_feet')->nullable();
        });

        // Add warehouse to location_type options if not exists
        DB::statement("ALTER TABLE locations MODIFY COLUMN location_type ENUM('funeral_home', 'cemetery', 'christy_vault', 'other')");
    }

    public function down()
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('radius_feet');
        });
    }
};
