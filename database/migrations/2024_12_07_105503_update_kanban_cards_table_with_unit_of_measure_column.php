<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kanban_cards', function (Blueprint $table) {
            $table->string('unit_of_measure')->nullable()->after('reorder_point');
        });
    }

    public function down(): void
    {
        Schema::table('kanban_cards', function (Blueprint $table) {
            $table->dropColumn('unit_of_measure');
        });
    }
};
