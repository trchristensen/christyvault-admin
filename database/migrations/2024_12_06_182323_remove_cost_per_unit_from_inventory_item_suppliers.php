<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
            if (Schema::hasColumn('inventory_item_suppliers', 'cost_per_unit')) {
                Schema::table('inventory_item_suppliers', function (Blueprint $table) {
                    $table->dropColumn('cost_per_unit');
                });
            }
    }

    public function down(): void
    {
        Schema::table('inventory_item_suppliers', function (Blueprint $table) {
            $table->decimal('cost_per_unit', 10, 2)->nullable();
        });
    }
};
