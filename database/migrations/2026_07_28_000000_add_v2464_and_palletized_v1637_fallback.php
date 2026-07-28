<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const V1637_PROFILE = 'christy_1637_vault_lower_bays_flatbed';

    private const V2464_PROFILE = 'christy_2464_vault_rack_flatbed';

    public function up(): void
    {
        Schema::table('loading_profiles', function (Blueprint $table): void {
            $table->unsignedSmallInteger('flatbed_fallback_units_per_pallet')
                ->nullable()
                ->after('flatbed_fallback_units_per_spot');
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
                'code' => self::V1637_PROFILE,
                'name' => 'V1637-1 — 4 per lower rack bay; 2 per fallback pallet',
                'handling_method' => 'individual',
                'units_per_pallet' => null,
                'units_per_rack_position' => 4,
                'flatbed_fallback_units_per_spot' => null,
                'flatbed_fallback_units_per_pallet' => 2,
                'full_load_units' => null,
                'pallet_compatibility_group' => null,
                'rack_requirement' => 'standard',
                'required_rack_level' => 'lower_not_top',
                'required_rack_type_id' => $preferredRackId,
                'placement_strategy' => 'one_per_level',
                'is_stackable' => true,
                'notes' => 'Four products fit in one lower rack bay. Use the bottom bay first; a middle bay is allowed in a 3-high rack. Never use the top bay. After compatible rack bays are full, palletize the remaining products two per pallet; each pallet consumes one fallback flatbed spot.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => self::V2464_PROFILE,
                'name' => 'V2464-1 — 1 per rack bay with direct flatbed fallback',
                'handling_method' => 'individual',
                'units_per_pallet' => null,
                'units_per_rack_position' => 1,
                'flatbed_fallback_units_per_spot' => 1,
                'flatbed_fallback_units_per_pallet' => null,
                'full_load_units' => null,
                'pallet_compatibility_group' => null,
                'rack_requirement' => 'standard',
                'required_rack_level' => 'any',
                'required_rack_type_id' => $preferredRackId,
                'placement_strategy' => 'one_per_level',
                'is_stackable' => true,
                'notes' => 'One product consumes one standard rack bay. Compatible with standard 2-high and 3-high racks. After rack bays are full, each remaining product consumes one flatbed spot and must be secured directly to the deck without a pallet.',
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
            'required_rack_type_id',
            'placement_strategy',
            'is_stackable',
            'notes',
            'is_active',
            'updated_at',
        ]);

        $profileIds = DB::table('loading_profiles')
            ->whereIn('code', [self::V1637_PROFILE, self::V2464_PROFILE])
            ->pluck('id', 'code');

        foreach ($profileIds as $profileId) {
            foreach ($rackTypes as $rackTypeId) {
                DB::table('loading_profile_rack_type')->insertOrIgnore([
                    'loading_profile_id' => $profileId,
                    'rack_type_id' => $rackTypeId,
                ]);
            }
        }

        $v2464ProfileId = $profileIds->get(self::V2464_PROFILE);

        if ($v2464ProfileId) {
            DB::table('products')
                ->whereRaw('UPPER(TRIM(sku)) = ?', ['V2464-1'])
                ->update([
                    'loading_profile_id' => $v2464ProfileId,
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        $v2464ProfileId = DB::table('loading_profiles')
            ->where('code', self::V2464_PROFILE)
            ->value('id');

        if ($v2464ProfileId) {
            DB::table('products')
                ->where('loading_profile_id', $v2464ProfileId)
                ->update(['loading_profile_id' => null]);
            DB::table('loading_profile_rack_type')
                ->where('loading_profile_id', $v2464ProfileId)
                ->delete();
            DB::table('loading_profiles')
                ->where('id', $v2464ProfileId)
                ->delete();
        }

        DB::table('loading_profiles')
            ->where('code', self::V1637_PROFILE)
            ->update([
                'name' => 'V1637-1 — 4 per lower rack bay with flatbed fallback',
                'flatbed_fallback_units_per_spot' => 1,
                'notes' => 'Four products fit in one lower rack bay. Use the bottom bay first; a middle bay is allowed in a 3-high rack. Never use the top bay. After compatible rack bays are full, each remaining product consumes one flatbed pallet-space position.',
                'updated_at' => now(),
            ]);

        Schema::table('loading_profiles', function (Blueprint $table): void {
            $table->dropColumn('flatbed_fallback_units_per_pallet');
        });
    }
};
