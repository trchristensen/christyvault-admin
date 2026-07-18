<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loading_profiles', function (Blueprint $table): void {
            $table->unsignedSmallInteger('full_load_units')
                ->nullable()
                ->after('units_per_pallet');
            $table->string('required_rack_level')
                ->default('any')
                ->after('rack_requirement');
            $table->foreignId('required_rack_type_id')
                ->nullable()
                ->after('required_rack_level')
                ->constrained('rack_types')
                ->nullOnDelete();
        });

        Schema::table('vehicle_configurations', function (Blueprint $table): void {
            $table->decimal('max_product_weight_lbs', 10, 2)
                ->nullable()
                ->after('rack_spot_count');
        });

        DB::table('vehicle_configurations')
            ->where('configuration_type', 'rack_trailer')
            ->update(['max_product_weight_lbs' => 38500]);

        $threeHighRackId = DB::table('rack_types')
            ->where('code', 'standard_3_high')
            ->value('id');
        $now = now();

        DB::table('loading_profiles')->upsert([
            [
                'code' => 'regular_burial_vault',
                'name' => 'Regular Wilbert burial vault',
                'handling_method' => 'individual',
                'units_per_pallet' => null,
                'full_load_units' => 15,
                'pallet_compatibility_group' => null,
                'rack_requirement' => 'standard',
                'required_rack_level' => 'any',
                'required_rack_type_id' => null,
                'is_stackable' => true,
                'notes' => 'Fifteen regular-size Wilbert burial vaults physically make a full load; the vehicle weight limit may reduce that quantity.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'regular_burial_vault_triune',
                'name' => 'Regular Wilbert Triune — bottom only',
                'handling_method' => 'individual',
                'units_per_pallet' => null,
                'full_load_units' => 15,
                'pallet_compatibility_group' => null,
                'rack_requirement' => 'standard',
                'required_rack_level' => 'bottom',
                'required_rack_type_id' => null,
                'is_stackable' => true,
                'notes' => 'Triunes go only on the bottom level so their finish is not scratched. Weight is the ultimate limit.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'standard_three_high_box',
                'name' => 'Standard box — 22 per 3-high load',
                'handling_method' => 'individual',
                'units_per_pallet' => null,
                'full_load_units' => 22,
                'pallet_compatibility_group' => null,
                'rack_requirement' => 'standard',
                'required_rack_level' => 'any',
                'required_rack_type_id' => $threeHighRackId,
                'is_stackable' => true,
                'notes' => 'Applies to G3086-6, V3086-1, and L3086-4. Twenty-two physically make a full load using 3-high racks.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'double_garden_crypt',
                'name' => 'Double garden crypt — 12 per full load',
                'handling_method' => 'individual',
                'units_per_pallet' => null,
                'full_load_units' => 12,
                'pallet_compatibility_group' => null,
                'rack_requirement' => 'standard',
                'required_rack_level' => 'any',
                'required_rack_type_id' => null,
                'is_stackable' => true,
                'notes' => 'Applies to G3086-4 and G3086-5 doubles. Twelve physically fit, but product weight may reduce the allowed quantity.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['code'], [
            'name',
            'handling_method',
            'units_per_pallet',
            'full_load_units',
            'pallet_compatibility_group',
            'rack_requirement',
            'required_rack_level',
            'required_rack_type_id',
            'is_stackable',
            'notes',
            'is_active',
            'updated_at',
        ]);
    }

    public function down(): void
    {
        DB::table('loading_profiles')
            ->whereIn('code', [
                'regular_burial_vault',
                'regular_burial_vault_triune',
                'standard_three_high_box',
                'double_garden_crypt',
            ])
            ->delete();

        Schema::table('vehicle_configurations', function (Blueprint $table): void {
            $table->dropColumn('max_product_weight_lbs');
        });

        Schema::table('loading_profiles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('required_rack_type_id');
            $table->dropColumn(['full_load_units', 'required_rack_level']);
        });
    }
};
