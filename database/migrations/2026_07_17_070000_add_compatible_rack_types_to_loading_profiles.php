<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loading_profile_rack_type', function (Blueprint $table): void {
            $table->foreignId('loading_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rack_type_id')->constrained()->cascadeOnDelete();
            $table->primary(['loading_profile_id', 'rack_type_id']);
        });

        DB::table('loading_profiles')
            ->whereNotNull('required_rack_type_id')
            ->orderBy('id')
            ->each(function (object $profile): void {
                DB::table('loading_profile_rack_type')->insertOrIgnore([
                    'loading_profile_id' => $profile->id,
                    'rack_type_id' => $profile->required_rack_type_id,
                ]);
            });

        $ringLinerProfileId = DB::table('loading_profiles')
            ->where('code', 'ring_liner_three_high')
            ->value('id');
        $twoHighRackId = DB::table('rack_types')
            ->where('code', 'standard_2_high')
            ->value('id');

        if ($ringLinerProfileId && $twoHighRackId) {
            DB::table('loading_profile_rack_type')->insertOrIgnore([
                'loading_profile_id' => $ringLinerProfileId,
                'rack_type_id' => $twoHighRackId,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('loading_profile_rack_type');
    }
};
