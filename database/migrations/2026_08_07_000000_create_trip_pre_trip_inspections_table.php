<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_pre_trip_inspections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('vehicle_configuration_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('truck_asset_id')->nullable()->constrained('maintenance_assets')->nullOnDelete();
            $table->foreignId('trailer_asset_id')->nullable()->constrained('maintenance_assets')->nullOnDelete();
            $table->foreignId('piggyback_asset_id')->nullable()->constrained('maintenance_assets')->nullOnDelete();
            $table->date('inspection_date')->index();
            $table->date('scheduled_date')->nullable()->index();
            $table->string('checklist_version');
            $table->string('status')->default('completed')->index();
            $table->boolean('safe_to_operate')->default(false)->index();
            $table->string('driver_name');
            $table->json('vehicle_configuration_snapshot')->nullable();
            $table->json('equipment_snapshot');
            $table->json('responses');
            $table->json('defects')->nullable();
            $table->text('defect_notes')->nullable();
            $table->text('certification_text');
            $table->timestamp('completed_at')->index();
            $table->timestamps();

            $table->index(['trip_id', 'driver_id', 'completed_at'], 'trip_pre_trip_driver_completed_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_pre_trip_inspections');
    }
};
