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
            $table->string('placement_strategy')
                ->default('one_per_level')
                ->after('required_rack_type_id');
        });

        $twoHighRackId = DB::table('rack_types')
            ->where('code', 'standard_2_high')
            ->value('id');

        DB::table('loading_profiles')
            ->where('code', 'double_garden_crypt')
            ->update([
                'required_rack_type_id' => $twoHighRackId,
                'placement_strategy' => 'full_top_split_bottom_pair',
                'notes' => 'G3086-4 and G3086-5 use 2-high racks. Each pair of racks carries two complete products on top plus one product split into halves across the two bottom levels. Eight racks carry 12 products.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('loading_profiles')
            ->where('code', 'double_garden_crypt')
            ->update([
                'required_rack_type_id' => null,
                'updated_at' => now(),
            ]);

        Schema::table('loading_profiles', function (Blueprint $table): void {
            $table->dropColumn('placement_strategy');
        });
    }
};
