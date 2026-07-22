<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('loading_profiles')->updateOrInsert(
            ['code' => 'loose_accessory'],
            [
                'name' => 'Loose / boxed accessory',
                'handling_method' => 'loose',
                'units_per_pallet' => null,
                'units_per_rack_position' => 1,
                'full_load_units' => null,
                'pallet_compatibility_group' => null,
                'rack_requirement' => 'none',
                'required_rack_level' => 'any',
                'required_rack_type_id' => null,
                'placement_strategy' => 'one_per_level',
                'is_stackable' => true,
                'notes' => 'Small miscellaneous cargo that contributes shipping weight but does not reserve a rack or pallet position.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    public function down(): void
    {
        DB::table('loading_profiles')
            ->where('code', 'loose_accessory')
            ->delete();
    }
};
