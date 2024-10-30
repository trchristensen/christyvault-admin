<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'eventable_type')) {
                $table->string('eventable_type')->nullable();
                $table->unsignedBigInteger('eventable_id')->nullable();
                $table->index(['eventable_type', 'eventable_id']);
            }
        });
    }

    public function down()
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['eventable_type', 'eventable_id']);
        });
    }
};
