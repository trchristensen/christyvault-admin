<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PROFILE_SKUS = [
        'garden_crypt_cover_6_lower_bays' => ['2-3086G5'],
        'christy_1637_vault_lower_bays_flatbed' => ['V1637-1'],
        'christy_1637_cover_4_per_pallet' => ['2-1637V1'],
    ];

    public function up(): void
    {
        Schema::table('loading_profiles', function (Blueprint $table): void {
            $table->unsignedSmallInteger('flatbed_fallback_units_per_spot')
                ->nullable()
                ->after('units_per_rack_position');
        });

        $rackTypes = DB::table('rack_types')
            ->whereIn('code', ['standard_2_high', 'standard_3_high'])
            ->pluck('id', 'code');
        $preferredRackId = $rackTypes->get('standard_3_high');

        if (! $preferredRackId) {
            return;
        }

        $now = now();
        DB::table('loading_profiles')->upsert([
            [
                'code' => 'garden_crypt_cover_6_lower_bays',
                'name' => '2-3086G5 cover — 6 per lower rack bay',
                'handling_method' => 'individual',
                'units_per_pallet' => null,
                'units_per_rack_position' => 6,
                'flatbed_fallback_units_per_spot' => null,
                'full_load_units' => null,
                'pallet_compatibility_group' => null,
                'rack_requirement' => 'standard',
                'required_rack_level' => 'lower_not_top',
                'required_rack_type_id' => $preferredRackId,
                'placement_strategy' => 'one_per_level',
                'is_stackable' => true,
                'notes' => 'Six covers fit in one bay. Use the bottom bay first; a middle bay is allowed in a 3-high rack. Never place these covers in the top bay. Compatible with standard 2-high and 3-high racks.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'christy_1637_vault_lower_bays_flatbed',
                'name' => 'V1637-1 — 4 per lower rack bay with flatbed fallback',
                'handling_method' => 'individual',
                'units_per_pallet' => null,
                'units_per_rack_position' => 4,
                'flatbed_fallback_units_per_spot' => 1,
                'full_load_units' => null,
                'pallet_compatibility_group' => null,
                'rack_requirement' => 'standard',
                'required_rack_level' => 'lower_not_top',
                'required_rack_type_id' => $preferredRackId,
                'placement_strategy' => 'one_per_level',
                'is_stackable' => true,
                'notes' => 'Four products fit in one lower rack bay. Use the bottom bay first; a middle bay is allowed in a 3-high rack. Never use the top bay. After compatible rack bays are full, each remaining product consumes one flatbed pallet-space position.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'christy_1637_cover_4_per_pallet',
                'name' => '2-1637V1 cover — 4 per pallet',
                'handling_method' => 'pallet',
                'units_per_pallet' => 4,
                'units_per_rack_position' => 1,
                'flatbed_fallback_units_per_spot' => null,
                'full_load_units' => null,
                'pallet_compatibility_group' => null,
                'rack_requirement' => 'standard',
                'required_rack_level' => 'lower_not_top',
                'required_rack_type_id' => $preferredRackId,
                'placement_strategy' => 'one_per_level',
                'is_stackable' => true,
                'notes' => 'Four covers fit on one pallet. Pallets use pallet-capable lower bays in standard 2-high or 3-high racks, never the top bay, with ordinary flatbed pallet-space fallback when rack pallet positions are full.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['code'], [
            'name',
            'handling_method',
            'units_per_pallet',
            'units_per_rack_position',
            'flatbed_fallback_units_per_spot',
            'full_load_units',
            'pallet_compatibility_group',
            'rack_requirement',
            'required_rack_level',
            'required_rack_type_id',
            'placement_strategy',
            'is_stackable',
            'notes',
            'is_active',
            'updated_at',
        ]);

        $profileIds = DB::table('loading_profiles')
            ->whereIn('code', array_keys(self::PROFILE_SKUS))
            ->pluck('id', 'code');

        foreach ($profileIds as $profileId) {
            foreach ($rackTypes as $rackTypeId) {
                DB::table('loading_profile_rack_type')->insertOrIgnore([
                    'loading_profile_id' => $profileId,
                    'rack_type_id' => $rackTypeId,
                ]);
            }
        }

        foreach (self::PROFILE_SKUS as $profileCode => $skus) {
            $profileId = $profileIds->get($profileCode);

            if (! $profileId) {
                continue;
            }

            DB::table('products')
                ->whereIn(DB::raw('UPPER(TRIM(sku))'), array_map(
                    fn (string $sku): string => mb_strtoupper(trim($sku)),
                    $skus,
                ))
                ->update([
                    'loading_profile_id' => $profileId,
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        $profileIds = DB::table('loading_profiles')
            ->whereIn('code', array_keys(self::PROFILE_SKUS))
            ->pluck('id');

        DB::table('products')
            ->whereIn('loading_profile_id', $profileIds)
            ->update(['loading_profile_id' => null]);
        DB::table('loading_profile_rack_type')
            ->whereIn('loading_profile_id', $profileIds)
            ->delete();
        DB::table('loading_profiles')
            ->whereIn('id', $profileIds)
            ->delete();

        Schema::table('loading_profiles', function (Blueprint $table): void {
            $table->dropColumn('flatbed_fallback_units_per_spot');
        });
    }
};
