<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_pre_trip_inspections', function (Blueprint $table): void {
            $table->string('report_type')->default('pre_trip')->index()->after('checklist_version');
            $table->timestamp('prior_report_reviewed_at')->nullable()->after('report_type');
        });

        Schema::create('trip_pre_trip_inspection_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inspection_id')->constrained('trip_pre_trip_inspections')->cascadeOnDelete();
            $table->foreignId('maintenance_asset_id')->constrained('maintenance_assets')->cascadeOnDelete();
            $table->string('role');
            $table->json('asset_snapshot')->nullable();
            $table->timestamps();

            $table->unique(['inspection_id', 'maintenance_asset_id', 'role'], 'inspection_asset_role_unique');
            $table->index(['maintenance_asset_id', 'inspection_id'], 'inspection_asset_history_index');
        });

        Schema::create('trip_pre_trip_inspection_defects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inspection_id')->constrained('trip_pre_trip_inspections')->cascadeOnDelete();
            $table->foreignId('maintenance_asset_id')->nullable()->constrained('maintenance_assets')->nullOnDelete();
            $table->foreignId('maintenance_request_id')->nullable()->constrained('maintenance_requests')->nullOnDelete();
            $table->foreignId('maintenance_work_order_id')->nullable()->constrained('maintenance_work_orders')->nullOnDelete();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('component_key');
            $table->string('component_label');
            $table->text('description');
            $table->boolean('safety_related')->default(true)->index();
            $table->string('status')->default('open')->index();
            $table->timestamp('reported_at')->index();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->text('resolution_certification')->nullable();
            $table->timestamps();

            $table->index(['maintenance_asset_id', 'status'], 'inspection_defect_asset_status_index');
        });

        DB::table('trip_pre_trip_inspections')
            ->orderBy('id')
            ->each(function (object $inspection): void {
                $snapshots = json_decode((string) $inspection->equipment_snapshot, true) ?: [];

                foreach (['truck', 'trailer', 'piggyback'] as $role) {
                    $assetId = $inspection->{$role.'_asset_id'} ?? null;

                    if (! $assetId) {
                        continue;
                    }

                    DB::table('trip_pre_trip_inspection_assets')->insert([
                        'inspection_id' => $inspection->id,
                        'maintenance_asset_id' => $assetId,
                        'role' => $role,
                        'asset_snapshot' => isset($snapshots[$role]) ? json_encode($snapshots[$role]) : null,
                        'created_at' => $inspection->created_at,
                        'updated_at' => $inspection->updated_at,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_pre_trip_inspection_defects');
        Schema::dropIfExists('trip_pre_trip_inspection_assets');

        Schema::table('trip_pre_trip_inspections', function (Blueprint $table): void {
            $table->dropColumn(['report_type', 'prior_report_reviewed_at']);
        });
    }
};
