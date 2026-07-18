<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $threeHighRackId = DB::table('rack_types')
            ->where('code', 'standard_3_high')
            ->value('id');
        $now = now();

        DB::table('loading_profiles')->upsert([
            [
                'code' => 'ring_liner_three_high',
                'name' => 'Ring liner — standard 3-high rack',
                'handling_method' => 'individual',
                'units_per_pallet' => null,
                'units_per_rack_position' => 1,
                'full_load_units' => null,
                'pallet_compatibility_group' => null,
                'rack_requirement' => 'standard',
                'required_rack_level' => 'any',
                'required_rack_type_id' => $threeHighRackId,
                'placement_strategy' => 'one_per_level',
                'is_stackable' => true,
                'notes' => 'L2472-4 may use a standard 3-high rack. No separate physical full-load quantity has been confirmed.',
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
    }

    public function down(): void
    {
        DB::table('loading_profiles')
            ->where('code', 'ring_liner_three_high')
            ->delete();
    }
};
