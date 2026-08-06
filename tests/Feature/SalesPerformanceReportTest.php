<?php

use App\Services\SalesPerformanceReport;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', ':memory:');

    Schema::create('locations', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('default_plant_location')->default('colma_main');
    });

    Schema::create('products', function (Blueprint $table): void {
        $table->id();
        $table->string('sku');
        $table->string('name');
        $table->string('product_type')->nullable();
        $table->decimal('price', 10, 2)->nullable();
    });

    Schema::create('orders', function (Blueprint $table): void {
        $table->id();
        $table->date('order_date')->nullable();
        $table->unsignedBigInteger('location_id')->nullable();
        $table->string('plant_location')->default('colma_main');
        $table->string('status');
        $table->softDeletes();
        $table->timestamps();
    });

    Schema::create('order_product', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('order_id');
        $table->unsignedBigInteger('product_id')->nullable();
        $table->string('custom_description')->nullable();
        $table->unsignedInteger('quantity')->nullable();
        $table->unsignedInteger('quantity_delivered')->nullable();
        $table->unsignedInteger('planned_fill_quantity')->nullable();
        $table->boolean('fill_load')->default(false);
        $table->decimal('price', 10, 2)->nullable();
    });

    Schema::create('sales_visits', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('location_id');
        $table->string('status');
        $table->timestamp('planned_at')->nullable();
        $table->timestamp('completed_at')->nullable();
        $table->timestamps();
    });

    DB::table('locations')->insert([
        ['id' => 1, 'name' => 'Cypress Lawn', 'default_plant_location' => 'colma_main'],
        ['id' => 2, 'name' => 'Other Cemetery', 'default_plant_location' => 'tulare_plant'],
    ]);

    DB::table('products')->insert([
        [
            'id' => 1,
            'sku' => 'V3086-1',
            'name' => 'Christy Vault',
            'product_type' => 'Wilbert Burial Vaults',
            'price' => 100,
        ],
        [
            'id' => 2,
            'sku' => 'W3086-M',
            'name' => 'Monticello',
            'product_type' => 'Wilbert Burial Vaults',
            'price' => 200,
        ],
        [
            'id' => 3,
            'sku' => 'UV1212V',
            'name' => 'Venetian Urn Vault',
            'product_type' => 'Wilbert Urn Vaults',
            'price' => 75,
        ],
    ]);

    DB::table('orders')->insert(array_map(
        fn (array $order): array => array_merge(['plant_location' => 'colma_main'], $order),
        [
            [
                'id' => 1,
                'order_date' => '2026-01-15',
                'location_id' => 1,
                'status' => 'confirmed',
                'created_at' => '2025-10-01 08:00:00',
                'updated_at' => '2025-10-01 08:00:00',
            ],
            [
                'id' => 2,
                'order_date' => '2026-02-10',
                'location_id' => 1,
                'status' => 'cancelled',
                'created_at' => '2026-02-10 08:00:00',
                'updated_at' => '2026-02-10 08:00:00',
            ],
            [
                'id' => 3,
                'order_date' => '2026-03-05',
                'location_id' => 1,
                'status' => 'confirmed',
                'created_at' => '2026-03-05 08:00:00',
                'updated_at' => '2026-03-05 08:00:00',
            ],
            [
                'id' => 4,
                'order_date' => '2026-04-12',
                'location_id' => 2,
                'plant_location' => 'tulare_plant',
                'status' => 'confirmed',
                'created_at' => '2026-04-12 08:00:00',
                'updated_at' => '2026-04-12 08:00:00',
            ],
            [
                'id' => 5,
                'order_date' => '2025-01-15',
                'location_id' => 1,
                'status' => 'confirmed',
                'created_at' => '2025-01-15 08:00:00',
                'updated_at' => '2025-01-15 08:00:00',
            ],
            [
                'id' => 6,
                'order_date' => '2025-03-05',
                'location_id' => 1,
                'status' => 'delivered',
                'created_at' => '2025-03-05 08:00:00',
                'updated_at' => '2025-03-05 08:00:00',
            ],
            [
                'id' => 7,
                'order_date' => '2026-06-20',
                'location_id' => 1,
                'plant_location' => 'colma_locals',
                'status' => 'pending',
                'created_at' => '2026-06-20 08:00:00',
                'updated_at' => '2026-06-20 08:00:00',
            ],
            [
                'id' => 8,
                'order_date' => '2026-07-29',
                'location_id' => 2,
                'plant_location' => 'tulare_plant',
                'status' => 'plant transfer',
                'created_at' => '2026-07-29 08:00:00',
                'updated_at' => '2026-07-29 08:00:00',
            ],
            [
                'id' => 9,
                'order_date' => '2025-07-20',
                'location_id' => 1,
                'status' => 'plant transferred',
                'created_at' => '2025-07-20 08:00:00',
                'updated_at' => '2025-07-20 08:00:00',
            ],
        ],
    ));

    $orderProductDefaults = [
        'product_id' => null,
        'custom_description' => null,
        'quantity' => null,
        'quantity_delivered' => null,
        'planned_fill_quantity' => null,
        'fill_load' => false,
        'price' => null,
    ];

    DB::table('order_product')->insert(array_map(
        fn (array $line): array => array_merge($orderProductDefaults, $line),
        [
            [
                'order_id' => 1,
                'product_id' => 1,
                'quantity' => 2,
                'fill_load' => false,
                'price' => 100,
            ],
            [
                'order_id' => 2,
                'product_id' => 1,
                'quantity' => 100,
                'fill_load' => false,
                'price' => 100,
            ],
            [
                'order_id' => 3,
                'product_id' => 2,
                'quantity' => null,
                'planned_fill_quantity' => 5,
                'fill_load' => true,
                'price' => 200,
            ],
            [
                'order_id' => 4,
                'product_id' => 3,
                'quantity' => 3,
                'fill_load' => false,
                'price' => 75,
            ],
            [
                'order_id' => 5,
                'product_id' => 1,
                'quantity' => 1,
                'fill_load' => false,
                'price' => 100,
            ],
            [
                'order_id' => 6,
                'product_id' => 2,
                'quantity' => null,
                'quantity_delivered' => 4,
                'planned_fill_quantity' => 6,
                'fill_load' => true,
                'price' => 200,
            ],
            [
                'order_id' => 7,
                'product_id' => null,
                'custom_description' => 'Miscellaneous setup item',
                'quantity' => 2,
                'fill_load' => false,
                'price' => 50,
            ],
            [
                'order_id' => 8,
                'product_id' => 1,
                'quantity' => 1000,
                'fill_load' => false,
                'price' => 100,
            ],
            [
                'order_id' => 9,
                'product_id' => 2,
                'quantity' => 1000,
                'fill_load' => false,
                'price' => 200,
            ],
        ],
    ));

    DB::table('sales_visits')->insert([
        [
            'location_id' => 1,
            'status' => 'completed',
            'planned_at' => '2026-01-10 09:00:00',
            'completed_at' => '2026-01-11 10:00:00',
            'created_at' => '2025-12-20 09:00:00',
            'updated_at' => '2026-01-11 10:00:00',
        ],
        [
            'location_id' => 1,
            'status' => 'planned',
            'planned_at' => '2026-02-10 09:00:00',
            'completed_at' => null,
            'created_at' => '2026-01-20 09:00:00',
            'updated_at' => '2026-01-20 09:00:00',
        ],
        [
            'location_id' => 1,
            'status' => 'completed',
            'planned_at' => '2025-01-10 09:00:00',
            'completed_at' => '2025-01-11 10:00:00',
            'created_at' => '2025-01-01 09:00:00',
            'updated_at' => '2025-01-11 10:00:00',
        ],
        [
            'location_id' => 2,
            'status' => 'completed',
            'planned_at' => '2026-04-10 09:00:00',
            'completed_at' => '2026-04-12 10:00:00',
            'created_at' => '2026-04-01 09:00:00',
            'updated_at' => '2026-04-12 10:00:00',
        ],
    ]);
});

it('builds a year-over-year report from order dates and excludes cancelled and plant transfer orders', function (): void {
    $report = app(SalesPerformanceReport::class)->build(
        locationId: '1',
        asOf: CarbonImmutable::parse('2026-07-30 12:00:00'),
    );

    expect($report['chart']['labels'])->toBe(['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'])
        ->and($report['chart']['datasets'][1]['data'])->toBe([2.0, 0, 5.0, 0, 0, 2.0, 0])
        ->and($report['chart']['datasets'][0]['data'])->toBe([1.0, 0, 4.0, 0, 0, 0, 0])
        ->and($report['summary']['currentValue'])->toBe(9.0)
        ->and($report['summary']['previousValue'])->toBe(5.0)
        ->and($report['summary']['currentOrders'])->toBe(3)
        ->and($report['summary']['completedVisits'])->toBe(1)
        ->and($report['summary']['previousCompletedVisits'])->toBe(1)
        ->and($report['summary']['dataThrough'])->toBe('Jun 20, 2026');

    $breakdown = collect($report['breakdown']['rows'])->keyBy('key');

    expect($breakdown['Wilbert Burial Vaults']['current'])->toBe(7.0)
        ->and($breakdown['Wilbert Burial Vaults']['previous'])->toBe(5.0)
        ->and($breakdown['Other']['current'])->toBe(2.0);
});

it('supports all locations, revenue, and product-level category drill-down', function (): void {
    $allLocations = app(SalesPerformanceReport::class)->build(
        locationId: 'all',
        metric: 'revenue',
        asOf: CarbonImmutable::parse('2026-07-30 12:00:00'),
    );

    expect($allLocations['summary']['currentValue'])->toBe(1525.0);

    $vaults = app(SalesPerformanceReport::class)->build(
        locationId: '1',
        productType: 'Wilbert Burial Vaults',
        asOf: CarbonImmutable::parse('2026-07-30 12:00:00'),
    );
    $products = collect($vaults['breakdown']['rows'])->keyBy('code');

    expect($vaults['breakdown']['level'])->toBe('product')
        ->and($vaults['summary']['currentValue'])->toBe(7.0)
        ->and($products['V3086-1']['current'])->toBe(2.0)
        ->and($products['V3086-1']['previous'])->toBe(1.0)
        ->and($products['W3086-M']['current'])->toBe(5.0)
        ->and($products['W3086-M']['previous'])->toBe(4.0);
});

it('filters every sales metric by each explicit plant location', function (): void {
    $colma = app(SalesPerformanceReport::class)->build(
        locationId: 'all',
        plants: ['colma_main'],
        asOf: CarbonImmutable::parse('2026-07-30 12:00:00'),
    );
    $locals = app(SalesPerformanceReport::class)->build(
        locationId: 'all',
        plants: ['colma_locals'],
        asOf: CarbonImmutable::parse('2026-07-30 12:00:00'),
    );
    $tulare = app(SalesPerformanceReport::class)->build(
        locationId: 'all',
        plants: ['tulare_plant'],
        asOf: CarbonImmutable::parse('2026-07-30 12:00:00'),
    );

    expect($colma['summary']['currentValue'])->toBe(7.0)
        ->and($colma['summary']['currentOrders'])->toBe(2)
        ->and($colma['summary']['completedVisits'])->toBe(1)
        ->and($locals['summary']['currentValue'])->toBe(2.0)
        ->and($locals['summary']['currentOrders'])->toBe(1)
        ->and($locals['summary']['completedVisits'])->toBe(0)
        ->and($tulare['summary']['currentValue'])->toBe(3.0)
        ->and($tulare['summary']['currentOrders'])->toBe(1)
        ->and($tulare['summary']['completedVisits'])->toBe(1)
        ->and($tulare['breakdown']['rows'])->toHaveCount(1)
        ->and($tulare['breakdown']['rows'][0]['key'])->toBe('Wilbert Urn Vaults');
});
