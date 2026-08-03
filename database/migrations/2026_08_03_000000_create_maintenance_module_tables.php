<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('maintenance_assets')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('asset_tag')->unique();
            $table->uuid('qr_token')->unique();
            $table->string('name');
            $table->string('category')->index();
            $table->string('status')->default('operational')->index();
            $table->string('criticality')->default('medium')->index();
            $table->text('description')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable()->index();
            $table->unsignedSmallInteger('year')->nullable();
            $table->date('acquired_on')->nullable();
            $table->date('warranty_expires_on')->nullable();
            $table->string('meter_type')->nullable();
            $table->decimal('current_meter', 14, 2)->nullable();
            $table->timestamp('meter_updated_at')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('manual_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('maintenance_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->nullable()->constrained('maintenance_assets')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('triaged_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('requester_name')->nullable();
            $table->string('requester_contact')->nullable();
            $table->string('title');
            $table->text('description');
            $table->string('priority')->default('normal')->index();
            $table->boolean('safety_related')->default(false)->index();
            $table->string('status')->default('new')->index();
            $table->json('photo_paths')->nullable();
            $table->text('triage_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('triaged_at')->nullable();
            $table->timestamps();
        });

        Schema::create('maintenance_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained('maintenance_assets')->cascadeOnDelete();
            $table->foreignId('default_assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('trigger_type')->default('calendar')->index();
            $table->unsignedInteger('interval_value')->default(1);
            $table->string('interval_unit')->nullable();
            $table->decimal('meter_interval', 14, 2)->nullable();
            $table->date('next_due_date')->nullable()->index();
            $table->decimal('next_due_meter', 14, 2)->nullable()->index();
            $table->unsignedInteger('lead_days')->default(7);
            $table->string('priority')->default('normal');
            $table->json('checklist')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamp('last_generated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('maintenance_work_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->nullable()->unique();
            $table->foreignId('asset_id')->nullable()->constrained('maintenance_assets')->nullOnDelete();
            $table->foreignId('request_id')->nullable()->unique()->constrained('maintenance_requests')->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('maintenance_plans')->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type')->default('reactive')->index();
            $table->string('priority')->default('normal')->index();
            $table->string('status')->default('approved')->index();
            $table->boolean('safety_related')->default(false)->index();
            $table->decimal('estimated_hours', 8, 2)->nullable();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('downtime_started_at')->nullable();
            $table->timestamp('downtime_ended_at')->nullable();
            $table->unsignedInteger('downtime_minutes')->nullable();
            $table->json('checklist')->nullable();
            $table->text('findings')->nullable();
            $table->text('work_performed')->nullable();
            $table->text('completion_notes')->nullable();
            $table->json('attachment_paths')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('maintenance_meter_readings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained('maintenance_assets')->cascadeOnDelete();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('reading', 14, 2);
            $table->timestamp('recorded_at')->index();
            $table->string('source')->default('manual');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('maintenance_work_order_labor_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('work_order_id')->constrained('maintenance_work_orders')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('minutes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('maintenance_work_order_parts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('work_order_id')->constrained('maintenance_work_orders')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('part_name');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_work_order_parts');
        Schema::dropIfExists('maintenance_work_order_labor_entries');
        Schema::dropIfExists('maintenance_meter_readings');
        Schema::dropIfExists('maintenance_work_orders');
        Schema::dropIfExists('maintenance_plans');
        Schema::dropIfExists('maintenance_requests');
        Schema::dropIfExists('maintenance_assets');
    }
};
