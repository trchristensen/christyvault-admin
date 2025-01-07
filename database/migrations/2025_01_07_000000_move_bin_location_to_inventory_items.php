<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Add fields to inventory_items
        Schema::table('inventory_items', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_items', 'storage_location')) {
                $table->string('storage_location')->nullable();
            }
            $table->string('bin_number')->nullable();
            $table->string('department')->nullable();
        });

        // Copy data from kanban_cards to inventory_items
        DB::statement('
            UPDATE inventory_items
            SET storage_location = COALESCE(storage_location, (
                SELECT bin_location
                FROM kanban_cards
                WHERE kanban_cards.inventory_item_id = inventory_items.id
                LIMIT 1
            )),
            bin_number = (
                SELECT bin_number
                FROM kanban_cards
                WHERE kanban_cards.inventory_item_id = inventory_items.id
                LIMIT 1
            ),
            department = (
                SELECT department
                FROM kanban_cards
                WHERE kanban_cards.inventory_item_id = inventory_items.id
                LIMIT 1
            )
        ');

        // Remove fields from kanban_cards
        Schema::table('kanban_cards', function (Blueprint $table) {
            $table->dropColumn(['bin_location', 'bin_number', 'department']);
        });
    }

    public function down()
    {
        // Add fields back to kanban_cards
        Schema::table('kanban_cards', function (Blueprint $table) {
            $table->string('bin_location')->nullable();
            $table->string('bin_number')->nullable();
            $table->string('department')->nullable();
        });

        // Copy data back
        DB::statement('
            UPDATE kanban_cards
            SET bin_location = (
                SELECT storage_location
                FROM inventory_items
                WHERE inventory_items.id = kanban_cards.inventory_item_id
            ),
            bin_number = (
                SELECT bin_number
                FROM inventory_items
                WHERE inventory_items.id = kanban_cards.inventory_item_id
            ),
            department = (
                SELECT department
                FROM inventory_items
                WHERE inventory_items.id = kanban_cards.inventory_item_id
            )
        ');

        // Remove added fields from inventory_items (keep storage_location)
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn(['bin_number', 'department']);
        });
    }
};
