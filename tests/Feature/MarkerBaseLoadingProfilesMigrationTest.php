<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', ':memory:');
    DB::purge('sqlite');

    Schema::create('rack_types', function (Blueprint $table): void {
        $table->id();
        $table->string('code')->unique();
    });

    Schema::create('loading_profiles', function (Blueprint $table): void {
        $table->id();
        $table->string('code')->unique();
        $table->string('name')->unique();
        $table->string('handling_method');
        $table->unsignedSmallInteger('units_per_pallet')->nullable();
        $table->unsignedSmallInteger('units_per_rack_position')->nullable();
        $table->unsignedSmallInteger('flatbed_fallback_units_per_spot')->nullable();
        $table->unsignedSmallInteger('flatbed_fallback_units_per_pallet')->nullable();
        $table->unsignedSmallInteger('full_load_units')->nullable();
        $table->string('pallet_compatibility_group')->nullable();
        $table->string('rack_requirement');
        $table->string('required_rack_level');
        $table->string('preferred_rack_level')->nullable();
        $table->unsignedBigInteger('required_rack_type_id')->nullable();
        $table->string('placement_strategy');
        $table->boolean('is_stackable');
        $table->text('notes')->nullable();
        $table->boolean('is_active');
        $table->timestamps();
    });

    Schema::create('products', function (Blueprint $table): void {
        $table->id();
        $table->string('sku');
        $table->string('name');
        $table->string('product_type')->nullable();
        $table->unsignedBigInteger('loading_profile_id')->nullable();
        $table->timestamps();
    });

    Schema::create('loading_profile_rack_type', function (Blueprint $table): void {
        $table->unsignedBigInteger('loading_profile_id');
        $table->unsignedBigInteger('rack_type_id');
        $table->primary(['loading_profile_id', 'rack_type_id']);
    });

    DB::table('rack_types')->insert([
        ['id' => 1, 'code' => 'standard_2_high'],
        ['id' => 2, 'code' => 'standard_3_high'],
    ]);

    DB::table('products')->insert([
        [
            'sku' => 'G2412NV',
            'name' => 'Granite Marker',
            'product_type' => 'Marker Foundations',
        ],
        [
            'sku' => 'G9999-CUSTOM',
            'name' => 'Custom Marker Base',
            'product_type' => 'Marker Foundations',
        ],
        [
            'sku' => 'B2211NV',
            'name' => 'Bronze Marker',
            'product_type' => 'Marker Foundations',
        ],
        [
            'sku' => 'B9999-CUSTOM',
            'name' => 'Custom Marker Base',
            'product_type' => 'Marker Foundations',
        ],
        [
            'sku' => 'W3086-B',
            'name' => 'The Wilbert Bronze',
            'product_type' => 'Wilbert Burial Vaults',
        ],
    ]);
});

it('creates marker base pallet profiles and assigns only marker products', function (): void {
    $migration = require database_path('migrations/2026_07_31_000000_add_marker_base_pallet_profiles.php');

    $migration->up();

    $profiles = DB::table('loading_profiles')->get()->keyBy('code');
    $granite = $profiles->get('granite_marker_bases_3_per_pallet');
    $bronze = $profiles->get('bronze_marker_bases_6_per_pallet');

    expect($granite)->not->toBeNull()
        ->and($granite->handling_method)->toBe('pallet')
        ->and($granite->units_per_pallet)->toBe(3)
        ->and($granite->required_rack_level)->toBe('lower_not_top')
        ->and($granite->required_rack_type_id)->toBe(2)
        ->and($bronze)->not->toBeNull()
        ->and($bronze->handling_method)->toBe('pallet')
        ->and($bronze->units_per_pallet)->toBe(6)
        ->and($bronze->required_rack_level)->toBe('lower_not_top')
        ->and($bronze->required_rack_type_id)->toBe(2)
        ->and(DB::table('loading_profile_rack_type')->count())->toBe(4);

    expect(DB::table('products')->where('sku', 'G2412NV')->value('loading_profile_id'))->toBe($granite->id)
        ->and(DB::table('products')->where('sku', 'G9999-CUSTOM')->value('loading_profile_id'))->toBe($granite->id)
        ->and(DB::table('products')->where('sku', 'B2211NV')->value('loading_profile_id'))->toBe($bronze->id)
        ->and(DB::table('products')->where('sku', 'B9999-CUSTOM')->value('loading_profile_id'))->toBe($bronze->id)
        ->and(DB::table('products')->where('sku', 'W3086-B')->value('loading_profile_id'))->toBeNull();

    $migration->down();

    expect(DB::table('loading_profiles')->count())->toBe(0)
        ->and(DB::table('loading_profile_rack_type')->count())->toBe(0)
        ->and(DB::table('products')->whereNotNull('loading_profile_id')->count())->toBe(0);
});
