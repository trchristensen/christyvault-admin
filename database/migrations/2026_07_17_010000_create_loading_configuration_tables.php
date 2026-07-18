<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loading_profiles')) {
            Schema::create('loading_profiles', function (Blueprint $table): void {
                $table->id();
                $table->string('code')->unique();
                $table->string('name')->unique();
                $table->string('handling_method')->default('individual');
                $table->unsignedSmallInteger('units_per_pallet')->nullable();
                $table->string('pallet_compatibility_group')->nullable();
                $table->string('rack_requirement')->default('standard');
                $table->boolean('is_stackable')->default(true);
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('rack_types')) {
            Schema::create('rack_types', function (Blueprint $table): void {
                $table->id();
                $table->string('code')->unique();
                $table->string('name')->unique();
                $table->unsignedTinyInteger('level_count');
                $table->unsignedTinyInteger('pallet_capable_levels')->default(0);
                $table->unsignedTinyInteger('pallets_per_capable_level')->default(2);
                $table->boolean('supports_standard_boxes')->default(true);
                $table->boolean('supports_oversized_boxes')->default(false);
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('vehicle_configurations')) {
            Schema::create('vehicle_configurations', function (Blueprint $table): void {
                $table->id();
                $table->string('code')->unique();
                $table->string('name')->unique();
                $table->string('configuration_type');
                $table->unsignedTinyInteger('rack_spot_count')->nullable();
                $table->boolean('piggyback_forklift_onboard')->default(false);
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('products', 'loading_profile_id')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->foreignId('loading_profile_id')
                    ->nullable()
                    ->after('weight_lbs')
                    ->constrained()
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('trips', 'vehicle_configuration_id')) {
            Schema::table('trips', function (Blueprint $table): void {
                $table->foreignId('vehicle_configuration_id')
                    ->nullable()
                    ->after('driver_id')
                    ->constrained()
                    ->nullOnDelete();
            });
        }

        $now = now();

        DB::table('loading_profiles')->upsert([
            [
                'code' => 'standard_rack_box',
                'name' => 'Standard rack box',
                'handling_method' => 'individual',
                'units_per_pallet' => null,
                'pallet_compatibility_group' => null,
                'rack_requirement' => 'standard',
                'is_stackable' => true,
                'notes' => 'Standard-size box that can use a normal 2-high or 3-high rack.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'oversized_single_rack',
                'name' => 'Oversized single-rack box',
                'handling_method' => 'individual',
                'units_per_pallet' => null,
                'pallet_compatibility_group' => null,
                'rack_requirement' => 'single',
                'is_stackable' => false,
                'notes' => 'Consumes one complete rack spot and cannot use a standard 2-high or 3-high rack.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'wilbert_urn_vault_pallet',
                'name' => 'Wilbert urn vault — 4 per pallet',
                'handling_method' => 'pallet',
                'units_per_pallet' => 4,
                'pallet_compatibility_group' => null,
                'rack_requirement' => 'standard',
                'is_stackable' => false,
                'notes' => 'Two pallets fit on each pallet-capable rack level.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['code'], [
            'name',
            'handling_method',
            'units_per_pallet',
            'pallet_compatibility_group',
            'rack_requirement',
            'is_stackable',
            'notes',
            'is_active',
            'updated_at',
        ]);

        DB::table('rack_types')->upsert([
            [
                'code' => 'standard_2_high',
                'name' => 'Standard 2-high rack',
                'level_count' => 2,
                'pallet_capable_levels' => 1,
                'pallets_per_capable_level' => 2,
                'supports_standard_boxes' => true,
                'supports_oversized_boxes' => false,
                'notes' => 'One lower pallet-capable level holds two pallets; the top level is not pallet-capable.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'standard_3_high',
                'name' => 'Standard 3-high rack',
                'level_count' => 3,
                'pallet_capable_levels' => 2,
                'pallets_per_capable_level' => 2,
                'supports_standard_boxes' => true,
                'supports_oversized_boxes' => false,
                'notes' => 'Two lower pallet-capable levels hold four pallets total; the top level is not pallet-capable.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'oversized_single',
                'name' => 'Oversized single rack',
                'level_count' => 1,
                'pallet_capable_levels' => 0,
                'pallets_per_capable_level' => 2,
                'supports_standard_boxes' => false,
                'supports_oversized_boxes' => true,
                'notes' => 'Uses one complete rack spot for one oversized vault or crypt.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['code'], [
            'name',
            'level_count',
            'pallet_capable_levels',
            'pallets_per_capable_level',
            'supports_standard_boxes',
            'supports_oversized_boxes',
            'notes',
            'is_active',
            'updated_at',
        ]);

        DB::table('vehicle_configurations')->upsert([
            [
                'code' => 'rack_trailer_forklift_onboard',
                'name' => 'Rack trailer — forklift onboard',
                'configuration_type' => 'rack_trailer',
                'rack_spot_count' => 8,
                'piggyback_forklift_onboard' => true,
                'notes' => 'Normal configuration. Unloads from the rear using the piggyback forklift.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'rack_trailer_forklift_at_site',
                'name' => 'Rack trailer — forklift already at site',
                'configuration_type' => 'rack_trailer',
                'rack_spot_count' => 10,
                'piggyback_forklift_onboard' => false,
                'notes' => 'Prebury configuration when the piggyback forklift has already been left at the site.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'boom_truck',
                'name' => 'Boom truck',
                'configuration_type' => 'boom_truck',
                'rack_spot_count' => null,
                'piggyback_forklift_onboard' => false,
                'notes' => 'No racks. Approximate context: 7 regular burial vaults or 14 compatible stackable boxes; exact rules still need confirmation.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['code'], [
            'name',
            'configuration_type',
            'rack_spot_count',
            'piggyback_forklift_onboard',
            'notes',
            'is_active',
            'updated_at',
        ]);
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('vehicle_configuration_id');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('loading_profile_id');
        });

        Schema::dropIfExists('vehicle_configurations');
        Schema::dropIfExists('rack_types');
        Schema::dropIfExists('loading_profiles');
    }
};
