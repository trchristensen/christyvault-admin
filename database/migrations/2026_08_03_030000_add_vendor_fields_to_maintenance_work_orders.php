<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_work_orders', function (Blueprint $table): void {
            $table->string('service_provider')->nullable()->after('assigned_to_user_id');
            $table->string('service_contact_name')->nullable()->after('service_provider');
            $table->string('service_phone')->nullable()->after('service_contact_name');
            $table->string('vendor_reference')->nullable()->after('service_phone');
            $table->string('purchase_order_number')->nullable()->after('vendor_reference');
            $table->decimal('authorization_limit', 12, 2)->nullable()->after('purchase_order_number');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_work_orders', function (Blueprint $table): void {
            $table->dropColumn([
                'service_provider',
                'service_contact_name',
                'service_phone',
                'vendor_reference',
                'purchase_order_number',
                'authorization_limit',
            ]);
        });
    }
};
