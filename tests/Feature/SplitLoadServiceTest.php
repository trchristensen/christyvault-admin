<?php

use App\Filament\Resources\TripResource\Pages\CreateTrip;
use App\Http\Controllers\OrderCalendarController;
use App\Models\Employee;
use App\Models\Order;
use App\Models\Trip;
use App\Models\TripStop;
use App\Services\DeliveryCalendarAvailability;
use App\Services\DeliveryTripService;
use App\Services\SplitLoadService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
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
        $table->boolean('is_active')->default(true);
        $table->timestamps();
        $table->softDeletes();
    });

    DB::table('employees')->insert([
        ['id' => 7, 'name' => 'Driver Seven', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 8, 'name' => 'Driver Eight', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 9, 'name' => 'Driver Nine', 'created_at' => now(), 'updated_at' => now()],
    ]);

    Schema::create('positions', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('display_name');
        $table->timestamps();
    });

    Schema::create('employee_position', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('employee_id');
        $table->unsignedBigInteger('position_id');
        $table->timestamps();
    });

    DB::table('positions')->insert([
        'id' => 1,
        'name' => 'driver',
        'display_name' => 'Driver',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('employee_position')->insert(collect([7, 8, 9])->map(fn (int $employeeId): array => [
        'employee_id' => $employeeId,
        'position_id' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ])->all());

    Schema::create('trips', function (Blueprint $table): void {
        $table->id();
        $table->string('trip_number')->unique();
        $table->unsignedBigInteger('driver_id')->nullable();
        $table->string('status')->default('pending');
        $table->date('scheduled_date');
        $table->timestamp('start_time')->nullable();
        $table->timestamp('end_time')->nullable();
        $table->text('notes')->nullable();
        $table->timestamp('dispatch_confirmed_at')->nullable();
        $table->unsignedBigInteger('dispatch_confirmed_by_user_id')->nullable();
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

    Schema::create('trip_stops', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('trip_id');
        $table->unsignedBigInteger('order_id')->nullable();
        $table->unsignedInteger('sequence')->nullable();
        $table->text('delivery_notes')->nullable();
        $table->timestamp('removed_at')->nullable();
        $table->timestamps();
    });
    DB::statement('CREATE UNIQUE INDEX trip_stops_active_sequence_unique ON trip_stops (trip_id, sequence) WHERE removed_at IS NULL');
    DB::statement('CREATE UNIQUE INDEX trip_stops_active_order_unique ON trip_stops (trip_id, order_id) WHERE removed_at IS NULL AND order_id IS NOT NULL');

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
        ->and($trip->dispatch_confirmed_at)->toBeNull()
        ->and($trip->isStopOrderConfirmed())->toBeFalse()
        ->and($trip->orders()->orderBy('stop_number')->pluck('id')->all())->toBe([1, 2])
        ->and($trip->orders()->orderBy('stop_number')->pluck('stop_number')->all())->toBe([1, 2])
        ->and($trip->orders()->get()->pluck('assigned_delivery_date')->map->toDateString()->unique()->all())->toBe(['2026-07-20'])
        ->and($trip->stops()->orderBy('sequence')->pluck('order_id')->all())->toBe([1, 2]);

    expect(Blade::render(
        '<x-delivery-trip-header :trip="$trip" :stop-count="2" />',
        compact('trip'),
    ))->toContain('Delivery plan needs review');

    app(SplitLoadService::class)->reverse($trip);

    expect($trip->orders()->orderBy('stop_number')->pluck('id')->all())->toBe([2, 1])
        ->and($trip->stops()->orderBy('sequence')->pluck('order_id')->all())->toBe([2, 1]);

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
        ->and(Order::count())->toBe(2)
        ->and(Order::whereNotNull('trip_id')->count())->toBe(2)
        ->and(Order::where('stop_number', 1)->count())->toBe(2)
        ->and(Trip::count())->toBe(2)
        ->and(TripStop::whereNull('removed_at')->count())->toBe(2)
        ->and(TripStop::whereNotNull('removed_at')->count())->toBe(2);
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

it('attaches one selected existing order without creating a duplicate order', function (): void {
    DB::table('orders')->insert(orderRow(1, 'ORD-00001', 7));

    $page = new class extends CreateTrip
    {
        public function createRecordForTest(array $data): Model
        {
            return $this->handleRecordCreation($data);
        }
    };

    $trip = $page->createRecordForTest([
        'driver_id' => 7,
        'status' => 'confirmed',
        'scheduled_date' => '2026-07-20',
        'orders' => [
            ['order_id' => 1, 'delivery_notes' => 'Use the rear gate'],
        ],
    ]);

    expect(Order::count())->toBe(1)
        ->and($trip->status)->toBe('confirmed')
        ->and($trip->orders()->pluck('id')->all())->toBe([1])
        ->and(Order::findOrFail(1)->trip_id)->toBe($trip->id)
        ->and(Order::findOrFail(1)->stop_number)->toBe(1)
        ->and(Order::findOrFail(1)->delivery_notes)->toBe('Use the rear gate');
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

    $service->updateDispatchPlan($trip, [1, 2, 3], 7);
    expect($trip->fresh()->isStopOrderConfirmed())->toBeTrue();

    $updatedTrip = $service->updateTrip($trip, [
        ['order_id' => 3],
        ['order_id' => 1, 'delivery_notes' => 'Call before arrival'],
        ['order_id' => 4],
    ], '2026-07-22', 9);

    expect($updatedTrip->is($trip))->toBeTrue()
        ->and($updatedTrip->trip_number)->toBe($tripNumber)
        ->and($updatedTrip->scheduled_date->toDateString())->toBe('2026-07-22')
        ->and($updatedTrip->driver_id)->toBe(9)
        ->and($updatedTrip->dispatch_confirmed_at)->toBeNull()
        ->and($updatedTrip->isStopOrderConfirmed())->toBeFalse()
        ->and($updatedTrip->orders()->orderBy('stop_number')->pluck('id')->all())->toBe([3, 1, 4])
        ->and($updatedTrip->orders()->orderBy('stop_number')->pluck('stop_number')->all())->toBe([1, 2, 3])
        ->and($updatedTrip->orders()->pluck('driver_id')->unique()->all())->toBe([9])
        ->and($updatedTrip->orders()->get()->pluck('assigned_delivery_date')->map->toDateString()->unique()->all())->toBe(['2026-07-22'])
        ->and($updatedTrip->orders()->find(1)?->delivery_notes)->toBe('Call before arrival')
        ->and(Order::findOrFail(2)->trip_id)->not->toBeNull()
        ->and(Order::findOrFail(2)->stop_number)->toBe(1)
        ->and(TripStop::where('trip_id', $updatedTrip->id)->whereNull('removed_at')->orderBy('sequence')->pluck('order_id')->all())->toBe([3, 1, 4]);
});

it('lets dispatch update only the driver and stop order', function (): void {
    DB::table('orders')->insert([
        orderRow(1, 'ORD-00001', 7),
        orderRow(2, 'ORD-00002', 7),
        orderRow(3, 'ORD-00003', 7),
    ]);

    $service = app(SplitLoadService::class);
    $trip = $service->createTrip([
        ['order_id' => 1],
        ['order_id' => 2],
        ['order_id' => 3],
    ], '2026-07-20', 7);

    $updatedTrip = $service->updateDispatchPlan($trip, [3, 1, 2], 9);

    expect($updatedTrip->is($trip))->toBeTrue()
        ->and($updatedTrip->driver_id)->toBe(9)
        ->and($updatedTrip->dispatch_confirmed_at)->not->toBeNull()
        ->and($updatedTrip->isStopOrderConfirmed())->toBeTrue()
        ->and($updatedTrip->scheduled_date->toDateString())->toBe('2026-07-20')
        ->and($updatedTrip->orders()->orderBy('stop_number')->pluck('id')->all())->toBe([3, 1, 2])
        ->and($updatedTrip->orders()->orderBy('stop_number')->pluck('stop_number')->all())->toBe([1, 2, 3])
        ->and($updatedTrip->orders()->pluck('driver_id')->unique()->all())->toBe([9]);

    expect(Blade::render(
        '<x-delivery-trip-header :trip="$updatedTrip" :stop-count="3" />',
        compact('updatedTrip'),
    ))->not->toContain('Delivery plan needs review');

    expect(fn () => $service->updateDispatchPlan($trip, [1, 2], 9))
        ->toThrow(ValidationException::class);
});

it('creates a one-stop trip for an eligible scheduled delivery', function (): void {
    DB::table('orders')->insert(orderRow(1, 'ORD-00001', 7));

    $trip = app(DeliveryTripService::class)
        ->ensureScheduledOrderHasTrip(Order::findOrFail(1));

    expect($trip)->not->toBeNull()
        ->and($trip->driver_id)->toBe(7)
        ->and($trip->scheduled_date->toDateString())->toBe('2026-07-20')
        ->and($trip->orders()->pluck('id')->all())->toBe([1])
        ->and($trip->stops()->pluck('order_id')->all())->toBe([1])
        ->and($trip->isStopOrderConfirmed())->toBeTrue()
        ->and(Order::findOrFail(1)->stop_number)->toBe(1);
});

it('lets dispatch assign the driver on a one-stop trip', function (): void {
    DB::table('orders')->insert(orderRow(1, 'ORD-00001', 7));

    $trip = app(DeliveryTripService::class)
        ->ensureScheduledOrderHasTrip(Order::findOrFail(1));

    $updatedTrip = app(SplitLoadService::class)->updateDispatchPlan($trip, [1], 9);

    expect($updatedTrip->driver_id)->toBe(9)
        ->and($updatedTrip->orders()->pluck('id')->all())->toBe([1])
        ->and($updatedTrip->orders()->value('driver_id'))->toBe(9)
        ->and($updatedTrip->stops()->pluck('order_id')->all())->toBe([1]);
});

it('automatically creates a one-stop trip when an order becomes a routed delivery', function (): void {
    $order = Order::create([
        'status' => 'will_call',
        'assigned_delivery_date' => '2026-07-20',
        'driver_id' => 7,
        'plant_location' => 'tulare_plant',
    ]);

    expect($order->fresh()->trip_id)->toBeNull();

    $order->update(['status' => 'confirmed']);
    $order->refresh();

    expect($order->trip_id)->not->toBeNull()
        ->and($order->stop_number)->toBe(1)
        ->and($order->trip?->driver_id)->toBe(7)
        ->and($order->activeTripStop?->sequence)->toBe(1);
});

it('merges one-stop trips without hard-deleting their history', function (): void {
    DB::table('orders')->insert([
        orderRow(1, 'ORD-00001', 7),
        orderRow(2, 'ORD-00002', 7),
    ]);

    $deliveryTrips = app(DeliveryTripService::class);
    $firstTrip = $deliveryTrips->ensureScheduledOrderHasTrip(Order::findOrFail(1));
    $secondTrip = $deliveryTrips->ensureScheduledOrderHasTrip(Order::findOrFail(2));

    $mergedTrip = app(SplitLoadService::class)->createTrip([
        ['order_id' => 1],
        ['order_id' => 2],
    ], '2026-07-20', 7);

    expect($mergedTrip->id)->toBe($firstTrip->id)
        ->and(Trip::count())->toBe(1)
        ->and(Trip::withTrashed()->count())->toBe(2)
        ->and(Trip::withTrashed()->findOrFail($secondTrip->id)->trashed())->toBeTrue()
        ->and($mergedTrip->stops()->orderBy('sequence')->pluck('order_id')->all())->toBe([1, 2])
        ->and(TripStop::where('trip_id', $secondTrip->id)->whereNotNull('removed_at')->count())->toBe(1)
        ->and(Order::whereKey([1, 2])->where('trip_id', $mergedTrip->id)->count())->toBe(2);
});

it('backfills safely in dry-run and apply modes', function (): void {
    DB::table('orders')->insert([
        orderRow(1, 'ORD-00001', 7),
        orderRow(2, 'ORD-00002', 7),
        orderRow(3, 'ORD-00003', 8),
    ]);

    $trip = Trip::create([
        'driver_id' => 7,
        'status' => 'pending',
        'scheduled_date' => '2026-07-20',
    ]);
    DB::table('orders')->whereIn('id', [1, 2])->update([
        'trip_id' => $trip->id,
    ]);
    DB::table('orders')->where('id', 1)->update(['stop_number' => 1]);
    DB::table('orders')->where('id', 2)->update(['stop_number' => 2]);

    $this->artisan('delivery-trips:backfill')->assertSuccessful();

    expect(TripStop::count())->toBe(0)
        ->and(Trip::count())->toBe(1)
        ->and(Order::findOrFail(3)->trip_id)->toBeNull();

    $this->artisan('delivery-trips:backfill --apply')->assertSuccessful();

    expect(TripStop::whereNull('removed_at')->count())->toBe(2)
        ->and(Trip::count())->toBe(1)
        ->and(Order::findOrFail(3)->trip_id)->toBeNull();

    $this->artisan('delivery-trips:backfill --apply --include-single-stop-trips')
        ->assertFailed();

    expect(Order::findOrFail(3)->trip_id)->toBeNull();

    $this->artisan('delivery-trips:backfill --apply --include-single-stop-trips --from=2026-07-20')->assertSuccessful();

    expect(Trip::count())->toBe(2)
        ->and(Order::findOrFail(3)->trip_id)->not->toBeNull()
        ->and(TripStop::whereNull('removed_at')->count())->toBe(3);
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

it('renders executable delivery photo actions with concrete record ids', function (): void {
    $order = new Order;
    $order->forceFill(['id' => 42]);

    $html = Blade::render(
        '<x-delivery-order-photo-button :order="$order" />',
        compact('order'),
    );

    expect($html)
        ->toContain('$wire.mountAction')
        ->toContain('{ order: 42 }')
        ->not->toContain('@js(');
});

it('omits the repeated driver from individual split load summaries', function (): void {
    $order = new Order;
    $order->forceFill([
        'id' => 42,
        'status' => 'confirmed',
        'is_printed' => false,
        'delivery_photos_count' => 0,
        'stop_number' => 1,
    ]);
    $order->setRelation('driver', (new Employee)->forceFill(['name' => 'Driver Seven']));
    $order->setRelation('trip', (new Trip)->forceFill(['id' => 10]));
    $order->setRelation('activeTripStop', null);
    $order->setRelation('location', null);

    $html = Blade::render(
        '<x-delivery-order-summary :order="$order" :is-delivery-trip="true" :stop-order-confirmed="true" :stop-count="2" />',
        compact('order'),
    );

    expect($html)
        ->toContain('Order #42')
        ->toContain('Upload delivery photos')
        ->not->toContain('Driver Seven');
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
