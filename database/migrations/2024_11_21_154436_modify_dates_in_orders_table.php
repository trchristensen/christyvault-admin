<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // First add the column with a default value
        Schema::table('orders', function (Blueprint $table) {
            $table->date('order_date')->default(now()->toDateString())->after('order_number');
        });

        // Then update it with the created_at dates
        DB::table('orders')->update([
            'order_date' => DB::raw('date(created_at)')
        ]);

        // Finally modify the existing date columns
        Schema::table('orders', function (Blueprint $table) {
            $table->date('requested_delivery_date')->change();
            $table->date('assigned_delivery_date')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('order_date');
            $table->dateTime('requested_delivery_date')->change();
            $table->dateTime('assigned_delivery_date')->nullable()->change();
        });
    }
};
