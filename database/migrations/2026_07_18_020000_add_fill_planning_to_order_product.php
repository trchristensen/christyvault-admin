<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_product', function (Blueprint $table): void {
            $table->unsignedInteger('planned_fill_quantity')->nullable()->after('fill_load');
            $table->unsignedInteger('fill_priority')->nullable()->after('planned_fill_quantity');
            $table->string('fill_plan_source')->nullable()->after('fill_priority');
            $table->timestamp('fill_locked_at')->nullable()->after('fill_plan_source');
        });
    }

    public function down(): void
    {
        Schema::table('order_product', function (Blueprint $table): void {
            $table->dropColumn([
                'planned_fill_quantity',
                'fill_priority',
                'fill_plan_source',
                'fill_locked_at',
            ]);
        });
    }
};
