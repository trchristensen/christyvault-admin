<?php

use App\Http\Controllers\OrderCalendarController;
use App\Models\Order;
use App\Models\Trip;
use App\Services\DeliveryCalendarAvailability;
use App\Services\SplitLoadService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Facades\Activity;

beforeEach(function (): void {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', ':memory:');
    config()->set('sms.driver_notifications.order_assignments', false);
    config()->set('sms.driver_notifications.status_updates', false);

    Schema::create('calendar_days', function (Blueprint $table): void {
        $table->id();
        $table->date('date');
        $table->string('name');
        $table->string('type');
        $table->boolean('blocks_delivery')->default(false);
        $table->boolean('opens_delivery')->default(false);
        $table->text('notes')->nullable();
        $table->timestamps();
    });

    Schema::create('employees', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
        $table->softDeletes();
    });

    DB::table('employees')->insert([
        ['id' => 7, 'name' => 'Driver Seven', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 8, 'name' => 'Driver Eight', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 9, 'name' => 'Driver Nine', 'created_at' => now(), 'updated_at' => now()],
    ]);

    Schema::create('trips', function (Blueprint $table): void {
        $table->id();
        $table->string('trip_number')->unique();
        $table->unsignedBigInteger('driver_id')->nullable();
        $table->string('status')->default('pending');
        $table->date('scheduled_date');
        $table->timestamp('start_time')->nullable();
        $table->timestamp('end_time')->nullable();
        $table->text('notes')->nullable();
        $table->uuid('uuid')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('orders', function (Blueprint $table): void {
        $table->id();
        $table->string('order_number');
        $table->string('status');
        $table->date('assigned_delivery_date')->nullable();
        $table->unsignedBigInteger('trip_id')->nullable();
        $table->unsignedInteger('stop_number')->nullable();
        $table->unsignedBigInteger('driver_id')->nullable();
        $table->string('plant_location')->nullable();
        $table->text('delivery_notes')->nullable();
        $table->uuid('uuid')->nullable();
        $table->timestamps();
        $table->softDeletes();
        $table->unique(['trip_id', 'stop_number']);
    });

    Activity::disableLogging();
});

it('creates, reorders, synchronizes, and dissolves a two-stop delivery trip', function (): void {
    DB::table('orders')->insert([
        orderRow(1, 'ORD-00001', 7),
        orderRow(2, 'ORD-00002', 7),
    ]);

    $trip = app(SplitLoadService::class)->create(
        Order::findOrFail(1),
        Order::findOrFail(2),
        '2026-07-20',
    );

    expect($trip->driver_id)->toBe(7)
        ->and($trip->orders()->orderBy('stop_number')->pluck('id')->all())->toBe([1, 2])
        ->and($trip->orders()->orderBy('stop_number')->pluck('stop_number')->all())->toBe([1, 2])
        ->and($trip->orders()->get()->pluck('assigned_delivery_date')->map->toDateString()->unique()->all())->toBe(['2026-07-20']);

    app(SplitLoadService::class)->reverse($trip);

    expect($trip->orders()->orderBy('stop_number')->pluck('id')->all())->toBe([2, 1]);

    $uuid = (string) $trip->uuid;
    $trip->update([
        'driver_id' => 9,
        'scheduled_date' => '2026-07-21',
    ]);

    expect((string) $trip->fresh()->uuid)->toBe($uuid)
        ->and($trip->orders()->pluck('driver_id')->unique()->all())->toBe([9])
        ->and($trip->orders()->get()->pluck('assigned_delivery_date')->map->toDateString()->unique()->all())->toBe(['2026-07-21']);

    app(SplitLoadService::class)->dissolve($trip);

    expect(Trip::find($trip->id))->toBeNull()
        ->and(Trip::withTrashed()->find($trip->id)?->trashed())->toBeTrue()
        ->and(Order::whereNotNull('trip_id')->count())->toBe(0)
        ->and(Order::whereNotNull('stop_number')->count())->toBe(0);
});

it('requires an explicit driver when the orders have different drivers', function (): void {
    DB::table('orders')->insert([
        orderRow(1, 'ORD-00001', 7),
        orderRow(2, 'ORD-00002', 8),
    ]);

    expect(fn () => app(SplitLoadService::class)->create(
        Order::findOrFail(1),
        Order::findOrFail(2),
        '2026-07-20',
    ))->toThrow(ValidationException::class);

    expect(Trip::count())->toBe(0)
        ->and(Order::whereNotNull('trip_id')->count())->toBe(0);
});

it('supports delivery trips with three or more reorderable stops', function (): void {
    DB::table('orders')->insert([
        orderRow(1, 'ORD-00001', 7),
        orderRow(2, 'ORD-00002', 7),
        orderRow(3, 'ORD-00003', 7),
    ]);

    $trip = app(SplitLoadService::class)->createTrip([
        ['order_id' => 1],
        ['order_id' => 2, 'delivery_notes' => 'Use the south gate'],
        ['order_id' => 3],
    ], '2026-07-20', 7);

    expect($trip->orders()->orderBy('stop_number')->pluck('id')->all())->toBe([1, 2, 3])
        ->and($trip->orders()->find(2)?->delivery_notes)->toBe('Use the south gate');

    app(SplitLoadService::class)->reverse($trip);

    expect($trip->orders()->orderBy('stop_number')->pluck('id')->all())->toBe([3, 2, 1])
        ->and($trip->orders()->orderBy('stop_number')->pluck('stop_number')->all())->toBe([1, 2, 3]);
});

it('updates an existing delivery trip without replacing it', function (): void {
    DB::table('orders')->insert([
        orderRow(1, 'ORD-00001', 7),
        orderRow(2, 'ORD-00002', 7),
        orderRow(3, 'ORD-00003', 7),
        orderRow(4, 'ORD-00004', 9),
    ]);

    $service = app(SplitLoadService::class);
    $trip = $service->createTrip([
        ['order_id' => 1],
        ['order_id' => 2],
        ['order_id' => 3],
    ], '2026-07-20', 7);
    $tripNumber = $trip->trip_number;

    $updatedTrip = $service->updateTrip($trip, [
        ['order_id' => 3],
        ['order_id' => 1, 'delivery_notes' => 'Call before arrival'],
        ['order_id' => 4],
    ], '2026-07-22', 9);

    expect($updatedTrip->is($trip))->toBeTrue()
        ->and($updatedTrip->trip_number)->toBe($tripNumber)
        ->and($updatedTrip->scheduled_date->toDateString())->toBe('2026-07-22')
        ->and($updatedTrip->driver_id)->toBe(9)
        ->and($updatedTrip->orders()->orderBy('stop_number')->pluck('id')->all())->toBe([3, 1, 4])
        ->and($updatedTrip->orders()->orderBy('stop_number')->pluck('stop_number')->all())->toBe([1, 2, 3])
        ->and($updatedTrip->orders()->pluck('driver_id')->unique()->all())->toBe([9])
        ->and($updatedTrip->orders()->get()->pluck('assigned_delivery_date')->map->toDateString()->unique()->all())->toBe(['2026-07-22'])
        ->and($updatedTrip->orders()->find(1)?->delivery_notes)->toBe('Call before arrival')
        ->and(Order::findOrFail(2)->trip_id)->toBeNull()
        ->and(Order::findOrFail(2)->stop_number)->toBeNull();
});

it('renders linked orders as one delivery trip calendar event', function (): void {
    DB::table('orders')->insert([
        orderRow(1, 'ORD-00001', 7),
        orderRow(2, 'ORD-00002', 7),
        orderRow(3, 'ORD-00003', 8),
    ]);

    $trip = app(SplitLoadService::class)->create(
        Order::findOrFail(1),
        Order::findOrFail(2),
        '2026-07-20',
    );

    $response = app(OrderCalendarController::class)->events(
        Request::create('/calendar-events', 'GET', [
            'start' => '2026-07-19',
            'end' => '2026-07-21',
        ]),
        app(DeliveryCalendarAvailability::class),
    );
    $events = collect($response->getData(true));
    $splitLoad = $events->first(fn (array $event): bool => data_get($event, 'extendedProps.type') === 'split_load');
    $standaloneOrders = $events->filter(fn (array $event): bool => data_get($event, 'extendedProps.type') === 'order');

    expect($splitLoad['id'])->toBe("trip_{$trip->id}")
        ->and(data_get($splitLoad, 'extendedProps.orders'))->toHaveCount(2)
        ->and(collect(data_get($splitLoad, 'extendedProps.orders'))->pluck('id')->all())->toBe([1, 2])
        ->and($standaloneOrders)->toHaveCount(1)
        ->and($standaloneOrders->first()['id'])->toBe('3');
});

function orderRow(int $id, string $orderNumber, ?int $driverId): array
{
    return [
        'id' => $id,
        'order_number' => $orderNumber,
        'status' => 'pending',
        'assigned_delivery_date' => '2026-07-20',
        'driver_id' => $driverId,
        'plant_location' => 'tulare_plant',
        'uuid' => (string) str()->uuid(),
        'created_at' => now(),
        'updated_at' => now(),
    ];
}
