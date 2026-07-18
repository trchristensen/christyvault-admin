<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $urnVaultProfileId = DB::table('loading_profiles')
            ->where('code', 'wilbert_urn_vault_pallet')
            ->value('id');
        $oversizedProfileId = DB::table('loading_profiles')
            ->where('code', 'oversized_single_rack')
            ->value('id');

        if ($urnVaultProfileId !== null) {
            DB::table('products')
                ->whereNull('loading_profile_id')
                ->whereRaw('UPPER(TRIM(sku)) LIKE ?', ['UV%'])
                ->update(['loading_profile_id' => $urnVaultProfileId]);
        }

        if ($oversizedProfileId !== null) {
            DB::table('products')
                ->whereNull('loading_profile_id')
                ->whereRaw('UPPER(TRIM(sku)) = ?', ['W3490-M'])
                ->update(['loading_profile_id' => $oversizedProfileId]);
        }
    }

    public function down(): void
    {
        $profileIds = DB::table('loading_profiles')
            ->whereIn('code', ['wilbert_urn_vault_pallet', 'oversized_single_rack'])
            ->pluck('id');

        DB::table('products')
            ->whereIn('loading_profile_id', $profileIds)
            ->where(function ($query): void {
                $query->whereRaw('UPPER(TRIM(sku)) LIKE ?', ['UV%'])
                    ->orWhereRaw('UPPER(TRIM(sku)) = ?', ['W3490-M']);
            })
            ->update(['loading_profile_id' => null]);
    }
};
