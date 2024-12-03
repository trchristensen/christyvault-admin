<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('type')->nullable();
            // While we're here, let's add color if you don't have it yet
            if (!Schema::hasColumn('events', 'color')) {
                $table->string('color')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('type');
            if (Schema::hasColumn('events', 'color')) {
                $table->dropColumn('color');
            }
        });
    }
};
