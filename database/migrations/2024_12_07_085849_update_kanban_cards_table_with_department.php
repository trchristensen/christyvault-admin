<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Add nullable fields first
        Schema::table('kanban_cards', function (Blueprint $table) {
            // Make bin_number nullable
            $table->string('bin_number')->nullable()->change();

            // Add new fields (both nullable initially)
            $table->string('department')->nullable()->after('bin_number');
            $table->string('description')->nullable()->after('department');
        });

        // Step 2: Set default values for existing records
        DB::statement("UPDATE kanban_cards SET department = 'General' WHERE department IS NULL");

        // Step 3: Now make department required
        Schema::table('kanban_cards', function (Blueprint $table) {
            $table->string('department')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kanban_cards', function (Blueprint $table) {
            // Restore original bin_number (not nullable)
            $table->string('bin_number')->change();

            // Remove new fields
            $table->dropColumn(['department', 'description']);
        });
    }
};
