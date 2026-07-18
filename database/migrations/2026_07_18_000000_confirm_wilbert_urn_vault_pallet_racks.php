<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $profileId = DB::table('loading_profiles')
            ->where('code', 'wilbert_urn_vault_pallet')
            ->value('id');
        $rackTypes = DB::table('rack_types')
            ->whereIn('code', ['standard_2_high', 'standard_3_high'])
            ->pluck('id', 'code');
        $twoHighRackId = $rackTypes->get('standard_2_high');

        if (! $profileId || ! $twoHighRackId) {
            return;
        }

        DB::table('loading_profiles')
            ->where('id', $profileId)
            ->update([
                'units_per_pallet' => 4,
                'required_rack_type_id' => $twoHighRackId,
                'notes' => 'All Wilbert urn vaults load four units per pallet. A partial pallet still consumes one pallet position. Standard 2-high and 3-high pallet-capable rack levels may be used.',
                'updated_at' => now(),
            ]);

        foreach ($rackTypes as $rackTypeId) {
            DB::table('loading_profile_rack_type')->insertOrIgnore([
                'loading_profile_id' => $profileId,
                'rack_type_id' => $rackTypeId,
            ]);
        }
    }

    public function down(): void
    {
        $profileId = DB::table('loading_profiles')
            ->where('code', 'wilbert_urn_vault_pallet')
            ->value('id');

        if (! $profileId) {
            return;
        }

        DB::table('loading_profile_rack_type')
            ->where('loading_profile_id', $profileId)
            ->delete();

        DB::table('loading_profiles')
            ->where('id', $profileId)
            ->update([
                'required_rack_type_id' => null,
                'updated_at' => now(),
            ]);
    }
};
