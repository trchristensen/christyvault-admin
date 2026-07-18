<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $twoHighRackId = DB::table('rack_types')
            ->where('code', 'standard_2_high')
            ->value('id');
        $profileIds = DB::table('loading_profiles')
            ->whereIn('code', [
                'regular_burial_vault',
                'regular_burial_vault_triune',
            ])
            ->pluck('id');

        if (! $twoHighRackId || $profileIds->isEmpty()) {
            return;
        }

        DB::table('loading_profiles')
            ->whereIn('id', $profileIds)
            ->update([
                'required_rack_type_id' => $twoHighRackId,
                'updated_at' => now(),
            ]);

        DB::table('loading_profile_rack_type')
            ->whereIn('loading_profile_id', $profileIds)
            ->delete();

        DB::table('loading_profile_rack_type')->insert(
            $profileIds->map(fn (int $profileId): array => [
                'loading_profile_id' => $profileId,
                'rack_type_id' => $twoHighRackId,
            ])->all(),
        );

        DB::table('loading_profiles')
            ->where('code', 'regular_burial_vault')
            ->update([
                'notes' => 'Regular-size Wilbert burial vaults use 2-high racks. No more than two burial vaults may be loaded in one rack. Fifteen physically make a full eight-rack load; the vehicle weight limit always takes priority.',
                'updated_at' => now(),
            ]);

        DB::table('loading_profiles')
            ->where('code', 'regular_burial_vault_triune')
            ->update([
                'notes' => 'Regular-size Wilbert Triunes use 2-high racks and must be placed on the bottom level so their finish is not scratched. No more than two burial vaults total may be loaded in one rack. Weight is the ultimate vehicle limit.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        $triuneProfileId = DB::table('loading_profiles')
            ->where('code', 'regular_burial_vault_triune')
            ->value('id');

        if (! $triuneProfileId) {
            return;
        }

        DB::table('loading_profile_rack_type')
            ->where('loading_profile_id', $triuneProfileId)
            ->delete();

        DB::table('loading_profiles')
            ->where('id', $triuneProfileId)
            ->update([
                'required_rack_type_id' => null,
                'updated_at' => now(),
            ]);
    }
};
