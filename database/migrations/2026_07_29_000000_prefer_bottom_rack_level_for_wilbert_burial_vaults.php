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
            $table->string('preferred_rack_level')
                ->nullable()
                ->after('required_rack_level');
        });

        DB::table('loading_profiles')
            ->where('code', 'regular_burial_vault')
            ->update([
                'preferred_rack_level' => 'bottom',
                'notes' => 'Regular-size Wilbert burial vaults use 2-high racks. Prefer bottom-level placement, but allow the top level when needed to preserve load capacity. No more than two burial vaults may be loaded in one rack. Fifteen physically make a full eight-rack load; the vehicle weight limit always takes priority.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('loading_profiles')
            ->where('code', 'regular_burial_vault')
            ->update([
                'notes' => 'Regular-size Wilbert burial vaults use 2-high racks. No more than two burial vaults may be loaded in one rack. Fifteen physically make a full eight-rack load; the vehicle weight limit always takes priority.',
                'updated_at' => now(),
            ]);

        Schema::table('loading_profiles', function (Blueprint $table): void {
            $table->dropColumn('preferred_rack_level');
        });
    }
};
