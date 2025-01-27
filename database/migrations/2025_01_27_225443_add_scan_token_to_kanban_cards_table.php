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
        Schema::table('kanban_cards', function (Blueprint $table) {
            $table->string('scan_token')->nullable()->unique();
        });

        // Generate tokens for existing cards
        DB::table('kanban_cards')
            ->whereNull('scan_token')
            ->chunkById(100, function ($cards) {
                foreach ($cards as $card) {
                    DB::table('kanban_cards')
                        ->where('id', $card->id)
                        ->update(['scan_token' => Str::random(32)]);
                }
            });
    }

    public function down()
    {
        Schema::table('kanban_cards', function (Blueprint $table) {
            $table->dropColumn('scan_token');
        });
    }
};
