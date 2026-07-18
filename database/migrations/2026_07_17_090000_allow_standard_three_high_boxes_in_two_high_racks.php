<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $profileId = DB::table('loading_profiles')
            ->where('code', 'standard_three_high_box')
            ->value('id');
        $twoHighRackId = DB::table('rack_types')
            ->where('code', 'standard_2_high')
            ->value('id');

        if (! $profileId || ! $twoHighRackId) {
            return;
        }

        DB::table('loading_profile_rack_type')->insertOrIgnore([
            'loading_profile_id' => $profileId,
            'rack_type_id' => $twoHighRackId,
        ]);

        DB::table('loading_profiles')
            ->where('id', $profileId)
            ->update([
                'notes' => 'G3086-6, V3086-1, and L3086-4 prefer 3-high racks for the confirmed 22-unit full load, but may use compatible openings in 2-high racks. Liners are preferred underneath whole G4/G5 doubles.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        $profileId = DB::table('loading_profiles')
            ->where('code', 'standard_three_high_box')
            ->value('id');
        $twoHighRackId = DB::table('rack_types')
            ->where('code', 'standard_2_high')
            ->value('id');

        if ($profileId && $twoHighRackId) {
            DB::table('loading_profile_rack_type')
                ->where('loading_profile_id', $profileId)
                ->where('rack_type_id', $twoHighRackId)
                ->delete();
        }
    }
};
