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
        // First create a temporary column
        Schema::table('order_product', function (Blueprint $table) {
            $table->tinyInteger('fill_load_int')->nullable()->after('fill_load');
        });

        // Copy data from boolean to integer
        DB::statement('UPDATE order_product SET fill_load_int = CASE WHEN fill_load THEN 1 ELSE 0 END');

        // Drop the boolean column
        Schema::table('order_product', function (Blueprint $table) {
            $table->dropColumn('fill_load');
        });

        // Rename the integer column to the original name
        Schema::table('order_product', function (Blueprint $table) {
            $table->renameColumn('fill_load_int', 'fill_load');
        });

        // Set not null constraint with default 0
        Schema::table('order_product', function (Blueprint $table) {
            $table->tinyInteger('fill_load')->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First create a temporary column
        Schema::table('order_product', function (Blueprint $table) {
            $table->boolean('fill_load_bool')->nullable()->after('fill_load');
        });

        // Copy data from integer to boolean
        DB::statement('UPDATE order_product SET fill_load_bool = CASE WHEN fill_load = 1 THEN TRUE ELSE FALSE END');

        // Drop the integer column
        Schema::table('order_product', function (Blueprint $table) {
            $table->dropColumn('fill_load');
        });

        // Rename the boolean column to the original name
        Schema::table('order_product', function (Blueprint $table) {
            $table->renameColumn('fill_load_bool', 'fill_load');
        });

        // Set not null constraint if needed
        Schema::table('order_product', function (Blueprint $table) {
            $table->boolean('fill_load')->default(false)->change();
        });
    }
}; 