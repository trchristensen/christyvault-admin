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
            $table->unsignedSmallInteger('units_per_rack_position')
                ->default(1)
                ->after('units_per_pallet');
        });

        $twoHighRackId = DB::table('rack_types')
            ->where('code', 'standard_2_high')
            ->value('id');

        DB::table('loading_profiles')
            ->where('code', 'garden_crypt_cover_4_high')
            ->update([
                'name' => 'Garden crypt cover — 4 per rack position',
                'required_rack_type_id' => $twoHighRackId,
                'units_per_rack_position' => 4,
                'notes' => 'Up to four G3086-5 covers stack together inside one level/position of a standard 2-high rack. The other rack level may hold another compatible Stop product.',
                'updated_at' => now(),
            ]);

        DB::table('rack_types')
            ->where('code', 'cover_4_high')
            ->delete();
    }

    public function down(): void
    {
        $now = now();

        DB::table('rack_types')->insert([
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
        ]);

        $coverRackId = DB::table('rack_types')
            ->where('code', 'cover_4_high')
            ->value('id');

        DB::table('loading_profiles')
            ->where('code', 'garden_crypt_cover_4_high')
            ->update([
                'name' => 'Garden crypt cover — 4 high',
                'required_rack_type_id' => $coverRackId,
                'notes' => 'G3086-5 covers stack bottom-to-top, up to four covers in one rack spot.',
                'updated_at' => $now,
            ]);

        Schema::table('loading_profiles', function (Blueprint $table): void {
            $table->dropColumn('units_per_rack_position');
        });
    }
};
