<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $rackTypes = DB::table('rack_types')
            ->whereIn('code', ['standard_2_high', 'standard_3_high'])
            ->pluck('id', 'code');
        $twoHighRackId = $rackTypes->get('standard_2_high');

        if (! $twoHighRackId) {
            return;
        }

        DB::table('loading_profiles')->upsert([
            [
                'code' => 'boxed_urn_products_18_per_pallet',
                'name' => 'Boxed urn products — 18 per pallet',
                'handling_method' => 'pallet',
                'units_per_pallet' => 18,
                'units_per_rack_position' => 1,
                'full_load_units' => null,
                'pallet_compatibility_group' => 'boxed_urn_products',
                'rack_requirement' => 'standard',
                'required_rack_level' => 'any',
                'required_rack_type_id' => $twoHighRackId,
                'placement_strategy' => 'one_per_level',
                'is_stackable' => true,
                'notes' => 'P300/P310-family boxed products load up to 18 per pallet. Compatible P-series products may share a pallet. Two pallets fit in each pallet-capable rack level.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'boxed_urn_products_9_per_pallet',
                'name' => 'Boxed urn products — 9 per pallet',
                'handling_method' => 'pallet',
                'units_per_pallet' => 9,
                'units_per_rack_position' => 1,
                'full_load_units' => null,
                'pallet_compatibility_group' => 'boxed_urn_products',
                'rack_requirement' => 'standard',
                'required_rack_level' => 'any',
                'required_rack_type_id' => $twoHighRackId,
                'placement_strategy' => 'one_per_level',
                'is_stackable' => true,
                'notes' => 'P400/P410-family boxed products load up to 9 per pallet. Compatible P-series products may share a pallet. Two pallets fit in each pallet-capable rack level.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['code'], [
            'name',
            'handling_method',
            'units_per_pallet',
            'units_per_rack_position',
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
            ->whereIn('code', [
                'boxed_urn_products_18_per_pallet',
                'boxed_urn_products_9_per_pallet',
            ])
            ->pluck('id');

        foreach ($profileIds as $profileId) {
            foreach ($rackTypes as $rackTypeId) {
                DB::table('loading_profile_rack_type')->insertOrIgnore([
                    'loading_profile_id' => $profileId,
                    'rack_type_id' => $rackTypeId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $profileIds = DB::table('loading_profiles')
            ->whereIn('code', [
                'boxed_urn_products_18_per_pallet',
                'boxed_urn_products_9_per_pallet',
            ])
            ->pluck('id');

        DB::table('loading_profile_rack_type')
            ->whereIn('loading_profile_id', $profileIds)
            ->delete();

        DB::table('loading_profiles')
            ->whereIn('id', $profileIds)
            ->delete();
    }
};
