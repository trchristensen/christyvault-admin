<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('rack_types')->upsert([
            [
                'code' => 'cover_4_high',
                'name' => 'Garden crypt cover stack — 4 high',
                'level_count' => 4,
                'pallet_capable_levels' => 0,
                'pallets_per_capable_level' => 2,
                'supports_standard_boxes' => false,
                'supports_oversized_boxes' => false,
                'notes' => 'One rack spot holds up to four individually stacked G3086-5 covers.',
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

        $twoHighRackId = DB::table('rack_types')->where('code', 'standard_2_high')->value('id');
        $coverRackId = DB::table('rack_types')->where('code', 'cover_4_high')->value('id');

        DB::table('loading_profiles')
            ->where('code', 'regular_burial_vault')
            ->update([
                'required_rack_type_id' => $twoHighRackId,
                'notes' => 'Regular-size Wilbert burial vaults use 2-high racks. Fifteen physically make a full eight-rack load; the vehicle weight limit always takes priority.',
                'updated_at' => $now,
            ]);

        DB::table('loading_profiles')->upsert([
            [
                'code' => 'garden_crypt_cover_4_high',
                'name' => 'Garden crypt cover — 4 high',
                'handling_method' => 'individual',
                'units_per_pallet' => null,
                'full_load_units' => null,
                'pallet_compatibility_group' => null,
                'rack_requirement' => 'standard',
                'required_rack_level' => 'any',
                'required_rack_type_id' => $coverRackId,
                'placement_strategy' => 'one_per_level',
                'is_stackable' => true,
                'notes' => 'G3086-5 covers stack bottom-to-top, up to four covers in one rack spot.',
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
            'placement_strategy',
            'is_stackable',
            'notes',
            'is_active',
            'updated_at',
        ]);
    }

    public function down(): void
    {
        DB::table('loading_profiles')
            ->where('code', 'regular_burial_vault')
            ->update([
                'required_rack_type_id' => null,
                'updated_at' => now(),
            ]);

        DB::table('loading_profiles')
            ->where('code', 'garden_crypt_cover_4_high')
            ->delete();

        DB::table('rack_types')
            ->where('code', 'cover_4_high')
            ->delete();
    }
};
