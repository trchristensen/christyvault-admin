<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const GRANITE_PROFILE = 'granite_marker_bases_3_per_pallet';

    private const BRONZE_PROFILE = 'bronze_marker_bases_6_per_pallet';

    private const GRANITE_SKUS = [
        'G1814V2-3.75',
        'G2412V2-4',
        'G2412V2-3.75',
        'G2412V2-3.75 (DEEP)',
        'G1814SV2-3.75',
        'G2412NV',
        'G2816NV',
        'G2412SV-4',
        'G1810V1-4',
        'G2412V1-5',
        'G2412V1-4',
    ];

    private const BRONZE_SKUS = [
        'B2211NV',
        'B2413NV',
        'B2414NV',
        'B3613V1-6',
        'B2412V1-5SL',
        'B2416V1-6',
        'B2412V2-3.75',
        'B2412V2-4',
        'B2816V2-4',
        'B2412V1-4',
        'B2412V1-5',
        'B2816V1-4',
    ];

    public function up(): void
    {
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
                'code' => self::GRANITE_PROFILE,
                'name' => 'Granite marker bases — 3 per pallet',
                'handling_method' => 'pallet',
                'units_per_pallet' => 3,
                'units_per_rack_position' => 1,
                'flatbed_fallback_units_per_spot' => null,
                'flatbed_fallback_units_per_pallet' => null,
                'full_load_units' => null,
                'pallet_compatibility_group' => null,
                'rack_requirement' => 'standard',
                'required_rack_level' => 'lower_not_top',
                'preferred_rack_level' => null,
                'required_rack_type_id' => $preferredRackId,
                'placement_strategy' => 'one_per_level',
                'is_stackable' => true,
                'notes' => 'Three granite marker bases fit on one pallet. Pallets use the lower pallet-capable levels of standard 2-high or 3-high racks, never the top level. Use ordinary flatbed pallet spots when rack pallet positions are unavailable. Different granite SKUs are not automatically mixed on one pallet.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => self::BRONZE_PROFILE,
                'name' => 'Bronze marker bases — 6 per pallet',
                'handling_method' => 'pallet',
                'units_per_pallet' => 6,
                'units_per_rack_position' => 1,
                'flatbed_fallback_units_per_spot' => null,
                'flatbed_fallback_units_per_pallet' => null,
                'full_load_units' => null,
                'pallet_compatibility_group' => null,
                'rack_requirement' => 'standard',
                'required_rack_level' => 'lower_not_top',
                'preferred_rack_level' => null,
                'required_rack_type_id' => $preferredRackId,
                'placement_strategy' => 'one_per_level',
                'is_stackable' => true,
                'notes' => 'Six bronze marker bases fit on one pallet. Pallets use the lower pallet-capable levels of standard 2-high or 3-high racks, never the top level. Use ordinary flatbed pallet spots when rack pallet positions are unavailable. Different bronze SKUs are not automatically mixed on one pallet.',
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
            'flatbed_fallback_units_per_pallet',
            'full_load_units',
            'pallet_compatibility_group',
            'rack_requirement',
            'required_rack_level',
            'preferred_rack_level',
            'required_rack_type_id',
            'placement_strategy',
            'is_stackable',
            'notes',
            'is_active',
            'updated_at',
        ]);

        $profileIds = DB::table('loading_profiles')
            ->whereIn('code', [self::GRANITE_PROFILE, self::BRONZE_PROFILE])
            ->pluck('id', 'code');

        foreach ($profileIds as $profileId) {
            foreach ($rackTypes as $rackTypeId) {
                DB::table('loading_profile_rack_type')->insertOrIgnore([
                    'loading_profile_id' => $profileId,
                    'rack_type_id' => $rackTypeId,
                ]);
            }
        }

        $this->assignMarkerProducts(
            self::GRANITE_SKUS,
            'G%',
            'granite',
            $profileIds->get(self::GRANITE_PROFILE),
            $now,
        );
        $this->assignMarkerProducts(
            self::BRONZE_SKUS,
            'B%',
            'bronze',
            $profileIds->get(self::BRONZE_PROFILE),
            $now,
        );
    }

    public function down(): void
    {
        $profileIds = DB::table('loading_profiles')
            ->whereIn('code', [self::GRANITE_PROFILE, self::BRONZE_PROFILE])
            ->pluck('id');

        DB::table('products')
            ->whereIn('loading_profile_id', $profileIds)
            ->update([
                'loading_profile_id' => null,
                'updated_at' => now(),
            ]);
        DB::table('loading_profile_rack_type')
            ->whereIn('loading_profile_id', $profileIds)
            ->delete();
        DB::table('loading_profiles')
            ->whereIn('id', $profileIds)
            ->delete();
    }

    private function assignMarkerProducts(
        array $skus,
        string $skuPrefix,
        string $material,
        ?int $profileId,
        mixed $now,
    ): void {
        if (! $profileId) {
            return;
        }

        DB::table('products')
            ->where(function ($query) use ($skus, $skuPrefix, $material): void {
                $query->whereIn(DB::raw('UPPER(TRIM(sku))'), $skus)
                    ->orWhere(function ($query) use ($material): void {
                        $query->whereRaw("LOWER(COALESCE(name, '')) LIKE ?", ["%{$material}%"])
                            ->whereRaw("LOWER(COALESCE(name, '')) LIKE ?", ['%marker%']);
                    })
                    ->orWhere(function ($query) use ($skuPrefix): void {
                        $query->whereRaw("LOWER(COALESCE(product_type, '')) = ?", ['marker foundations'])
                            ->whereRaw('UPPER(TRIM(sku)) LIKE ?', [$skuPrefix]);
                    });
            })
            ->update([
                'loading_profile_id' => $profileId,
                'updated_at' => $now,
            ]);
    }
};
