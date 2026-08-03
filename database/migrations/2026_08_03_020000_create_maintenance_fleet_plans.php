<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_fleet_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('default_assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('manufacturer')->nullable()->index();
            $table->string('asset_category')->nullable()->index();
            $table->string('meter_type')->default('hours');
            $table->decimal('meter_interval', 14, 2);
            $table->string('service_provider')->nullable();
            $table->string('service_contact_name')->nullable();
            $table->string('service_phone')->nullable();
            $table->string('priority')->default('normal');
            $table->json('checklist')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamp('last_generated_at')->nullable();
            $table->timestamp('last_completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('maintenance_fleet_plan_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fleet_plan_id')->constrained('maintenance_fleet_plans')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('maintenance_assets')->cascadeOnDelete();
            $table->boolean('included')->default(true)->index();
            $table->boolean('matches_filter')->default(true)->index();
            $table->decimal('baseline_meter', 14, 2)->nullable();
            $table->decimal('next_due_meter', 14, 2)->nullable()->index();
            $table->timestamp('last_serviced_at')->nullable();
            $table->timestamps();

            $table->unique(['fleet_plan_id', 'asset_id']);
        });

        Schema::create('maintenance_fleet_service_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fleet_plan_id')->constrained('maintenance_fleet_plans')->cascadeOnDelete();
            $table->foreignId('triggered_by_asset_id')->nullable()->constrained('maintenance_assets')->nullOnDelete();
            $table->string('status')->default('open')->index();
            $table->timestamp('generated_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('maintenance_work_orders', function (Blueprint $table): void {
            $table->foreignId('fleet_service_run_id')
                ->nullable()
                ->after('plan_id')
                ->constrained('maintenance_fleet_service_runs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_work_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('fleet_service_run_id');
        });

        Schema::dropIfExists('maintenance_fleet_service_runs');
        Schema::dropIfExists('maintenance_fleet_plan_assets');
        Schema::dropIfExists('maintenance_fleet_plans');
    }
};
