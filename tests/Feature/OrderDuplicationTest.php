<?php

use App\Http\Controllers\OrderController;
use App\Models\Order;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Facades\Activity;

beforeEach(function (): void {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', ':memory:');

    Schema::create('orders', function (Blueprint $table): void {
        $table->id();
        $table->uuid('uuid')->unique();
        $table->string('order_number')->unique();
        $table->date('requested_delivery_date');
        $table->text('special_instructions')->nullable();
        $table->string('status')->default('pending');
        $table->softDeletes();
        $table->unsignedBigInteger('customer_id')->nullable();
        $table->date('assigned_delivery_date')->nullable();
        $table->unsignedBigInteger('trip_id')->nullable();
        $table->unsignedInteger('stop_number')->nullable();
        $table->timestamp('delivered_at')->nullable();
        $table->string('signature_path')->nullable();
        $table->text('delivery_notes')->nullable();
        $table->date('order_date');
        $table->unsignedBigInteger('location_id')->nullable();
        $table->time('delivery_time')->nullable();
        $table->timestamp('service_date')->nullable();
        $table->timestamp('arrived_at')->nullable();
        $table->string('plant_location')->nullable();
        $table->string('customer_order_number')->nullable();
        $table->unsignedBigInteger('driver_id')->nullable();
        $table->string('ordered_by')->nullable();
        $table->boolean('is_printed')->default(false);
        $table->string('delivery_tag_url')->nullable();
        $table->timestamps();
        $table->unique(['trip_id', 'stop_number'], 'orders_trip_stop_unique');
    });

    Schema::create('order_product', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('order_id');
        $table->unsignedBigInteger('product_id')->nullable();
        $table->text('custom_description')->nullable();
        $table->boolean('is_custom_product')->default(false);
        $table->unsignedInteger('quantity')->nullable();
        $table->unsignedInteger('quantity_delivered')->nullable();
        $table->text('delivery_notes')->nullable();
        $table->decimal('price', 10, 2)->nullable();
        $table->text('notes')->nullable();
        $table->string('location')->nullable();
        $table->boolean('fill_load')->default(false);
        $table->unsignedInteger('planned_fill_quantity')->nullable();
        $table->unsignedInteger('fill_priority')->nullable();
        $table->string('fill_plan_source')->nullable();
        $table->timestamp('fill_locked_at')->nullable();
        $table->timestamps();
    });

    Activity::disableLogging();
});

it('duplicates order contents without copying trip, dispatch, delivery, or fill-plan state', function (): void {
    DB::table('orders')->insert([
        'id' => 1,
        'uuid' => '2d90b7d7-6d2d-4ea1-859e-f1e30b5c3cd1',
        'order_number' => 'ORD-00222',
        'requested_delivery_date' => '2026-07-24',
        'special_instructions' => 'Call the office before delivery.',
        'status' => 'delivered',
        'customer_id' => 12,
        'assigned_delivery_date' => '2026-07-24',
        'trip_id' => 80,
        'stop_number' => 1,
        'delivered_at' => '2026-07-24 11:30:00',
        'signature_path' => 'signatures/old.png',
        'delivery_notes' => 'Use the north gate.',
        'order_date' => '2026-07-10',
        'location_id' => null,
        'delivery_time' => '10:30:00',
        'service_date' => '2026-07-24 10:30:00',
        'arrived_at' => '2026-07-24 10:15:00',
        'plant_location' => 'colma_main',
        'customer_order_number' => 'PO-OLD-123',
        'driver_id' => 6,
        'ordered_by' => 'Miguel',
        'is_printed' => true,
        'delivery_tag_url' => 'delivery-tags/old.pdf',
        'created_at' => '2026-07-10 09:00:00',
        'updated_at' => '2026-07-24 11:30:00',
    ]);
    DB::table('order_product')->insert([
        'id' => 1,
        'order_id' => 1,
        'product_id' => 9,
        'quantity' => 5,
        'quantity_delivered' => 5,
        'delivery_notes' => 'Keep upright.',
        'price' => 100,
        'notes' => 'Copied product note.',
        'location' => 'Rack A',
        'fill_load' => true,
        'planned_fill_quantity' => 8,
        'fill_priority' => 2,
        'fill_plan_source' => 'automatic',
        'fill_locked_at' => '2026-07-24 09:00:00',
        'created_at' => '2026-07-10 09:00:00',
        'updated_at' => '2026-07-24 11:30:00',
    ]);

    $response = (new OrderController)->duplicate(Order::findOrFail(1));
    $duplicate = Order::query()->whereKeyNot(1)->firstOrFail();
    $duplicateLine = DB::table('order_product')->where('order_id', $duplicate->getKey())->first();

    expect($response->isRedirect())->toBeTrue()
        ->and($duplicate->order_number)->toBe('ORD-00223')
        ->and($duplicate->uuid)->not->toBe('2d90b7d7-6d2d-4ea1-859e-f1e30b5c3cd1')
        ->and($duplicate->status)->toBe('pending')
        ->and($duplicate->order_date->toDateString())->toBe(now()->toDateString())
        ->and($duplicate->trip_id)->toBeNull()
        ->and($duplicate->stop_number)->toBeNull()
        ->and($duplicate->driver_id)->toBeNull()
        ->and($duplicate->assigned_delivery_date)->toBeNull()
        ->and($duplicate->delivery_time)->toBeNull()
        ->and($duplicate->service_date)->toBeNull()
        ->and($duplicate->arrived_at)->toBeNull()
        ->and($duplicate->delivered_at)->toBeNull()
        ->and($duplicate->signature_path)->toBeNull()
        ->and($duplicate->is_printed)->toBeFalse()
        ->and($duplicate->delivery_tag_url)->toBeNull()
        ->and($duplicate->customer_order_number)->toBeNull()
        ->and($duplicate->requested_delivery_date->toDateString())->toBe('2026-07-24')
        ->and($duplicate->special_instructions)->toBe('Call the office before delivery.')
        ->and($duplicate->delivery_notes)->toBe('Use the north gate.')
        ->and($duplicate->customer_id)->toBe(12)
        ->and($duplicate->location_id)->toBeNull()
        ->and($duplicate->plant_location)->toBe('colma_main')
        ->and($duplicate->ordered_by)->toBe('Miguel')
        ->and(DB::table('orders')->where('trip_id', 80)->where('stop_number', 1)->count())->toBe(1)
        ->and($duplicateLine)->not->toBeNull()
        ->and($duplicateLine->product_id)->toBe(9)
        ->and($duplicateLine->quantity)->toBe(5)
        ->and($duplicateLine->quantity_delivered)->toBeNull()
        ->and($duplicateLine->delivery_notes)->toBe('Keep upright.')
        ->and($duplicateLine->fill_load)->toBe(1)
        ->and($duplicateLine->fill_priority)->toBe(2)
        ->and($duplicateLine->planned_fill_quantity)->toBeNull()
        ->and($duplicateLine->fill_plan_source)->toBeNull()
        ->and($duplicateLine->fill_locked_at)->toBeNull();
});
