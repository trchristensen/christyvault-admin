<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_vendors', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->text('services_provided')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('maintenance_work_orders', function (Blueprint $table): void {
            $table->foreignId('maintenance_vendor_id')
                ->nullable()
                ->after('assigned_to_user_id')
                ->constrained('maintenance_vendors')
                ->nullOnDelete();
        });

        Schema::table('maintenance_fleet_plans', function (Blueprint $table): void {
            $table->foreignId('maintenance_vendor_id')
                ->nullable()
                ->after('default_assignee_id')
                ->constrained('maintenance_vendors')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_fleet_plans', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('maintenance_vendor_id');
        });

        Schema::table('maintenance_work_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('maintenance_vendor_id');
        });

        Schema::dropIfExists('maintenance_vendors');
    }
};
