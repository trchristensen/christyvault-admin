<?php

use App\Filament\Actions\TripLoadSummaryAction;
use App\Filament\Resources\OrderResource\Pages\DeliveryCalendar;
use App\Livewire\OrderModal;
use App\Models\Order;
use App\Models\Trip;
use App\Models\User;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Blade;

function tripWithDeliveryTagStates(bool ...$printedStates): Trip
{
    $trip = (new Trip)->forceFill([
        'id' => 42,
        'trip_number' => 'TRIP-00042',
    ]);
    $orders = collect($printedStates)->map(function (bool $isPrinted, int $index) use ($trip): Order {
        $order = (new Order)->forceFill([
            'id' => $index + 1,
            'trip_id' => 42,
            'is_printed' => $isPrinted,
        ]);
        $order->setRelation('trip', $trip);

        return $order;
    });
    $trip->setRelation('stops', collect());
    $trip->setRelation('orders', $orders);

    return $trip;
}

function loadSummaryViewer(bool $mayView, bool $mayViewUnprinted = false): User
{
    $user = new class extends User
    {
        public bool $mayViewLoadSummary = false;

        public bool $mayViewUnprintedProductLines = false;

        public function can($abilities, $arguments = []): bool
        {
            return match ($abilities) {
                'view load summary' => $this->mayViewLoadSummary,
                Order::VIEW_UNPRINTED_PRODUCT_LINES_PERMISSION => $this->mayViewUnprintedProductLines,
                default => false,
            };
        }
    };
    $user->mayViewLoadSummary = $mayView;
    $user->mayViewUnprintedProductLines = $mayViewUnprinted;

    return $user;
}

it('requires both a trip and the view load summary permission', function () {
    $unassignedOrder = new Order;
    $assignedOrder = tripWithDeliveryTagStates(true)->orders->first();
    $user = loadSummaryViewer(false);
    auth()->setUser($user);

    expect(TripLoadSummaryAction::make()->record($unassignedOrder)->isVisible())->toBeFalse()
        ->and(TripLoadSummaryAction::make()->record($assignedOrder)->isVisible())->toBeFalse();

    $user->mayViewLoadSummary = true;

    expect(TripLoadSummaryAction::make()->record($assignedOrder)->isVisible())->toBeTrue();
});

it('uses the same permission for the calendar order modal', function () {
    $modal = new OrderModal;
    $modal->order = tripWithDeliveryTagStates(true)->orders->first();
    $user = loadSummaryViewer(false);
    auth()->setUser($user);

    expect($modal->canViewLoadSummary())->toBeFalse();

    $user->mayViewLoadSummary = true;

    expect($modal->canViewLoadSummary())->toBeTrue();
});

it('shows the load summary in the saved trip edit modal with the same permission', function () {
    $page = new class extends DeliveryCalendar
    {
        public Trip $tripForTest;

        public function tripEditorActionForTest(): Action
        {
            return collect($this->getHeaderActions())
                ->first(fn (Action $action): bool => $action->getName() === 'createSplitLoad');
        }

        protected function tripForLoadSummary(int $tripId): Trip
        {
            return $this->tripForTest;
        }
    };
    $page->tripForTest = tripWithDeliveryTagStates(true);
    $user = loadSummaryViewer(false);
    auth()->setUser($user);
    $loadSummaryAction = $page->tripEditorActionForTest()
        ->livewire($page)
        ->getExtraModalFooterActions()['viewTripLoadSummary'];

    expect($loadSummaryAction->isVisible())->toBeFalse();

    $page->editingTripId = 42;

    expect($loadSummaryAction->isVisible())->toBeFalse();

    $user->mayViewLoadSummary = true;

    expect($loadSummaryAction->isVisible())->toBeTrue();
});

it('shows the team schedule load summary trigger only with permission', function () {
    $user = loadSummaryViewer(false);
    auth()->setUser($user);
    $trip = tripWithDeliveryTagStates(true);

    $deniedHtml = Blade::render(
        '<x-delivery-trip-load-summary-button :trip="$trip" />',
        compact('trip'),
    );

    $user->mayViewLoadSummary = true;
    $allowedHtml = Blade::render(
        '<x-delivery-trip-load-summary-button :trip="$trip" />',
        compact('trip'),
    );

    expect(trim($deniedHtml))->toBe('')
        ->and($allowedHtml)->toContain('viewDeliveryTripLoadSummary')
        ->and($allowedHtml)->toContain('trip: 42')
        ->and($allowedHtml)->toContain('Load summary');
});

it('holds every load summary until all tags are printed unless the viewer has the bypass permission', function () {
    $user = loadSummaryViewer(true);
    auth()->setUser($user);
    $trip = tripWithDeliveryTagStates(true, false);
    $order = $trip->orders->first();

    $heldHtml = Blade::render(
        '<x-delivery-trip-load-summary-button :trip="$trip" />',
        compact('trip'),
    );

    expect($trip->loadSummaryIsVisibleTo($user))->toBeFalse()
        ->and(TripLoadSummaryAction::make()->record($order)->isVisible())->toBeFalse()
        ->and(trim($heldHtml))->toBe('');

    $user->mayViewUnprintedProductLines = true;
    $allowedHtml = Blade::render(
        '<x-delivery-trip-load-summary-button :trip="$trip" />',
        compact('trip'),
    );

    expect($trip->loadSummaryIsVisibleTo($user))->toBeTrue()
        ->and(TripLoadSummaryAction::make()->record($order)->isVisible())->toBeTrue()
        ->and($allowedHtml)->toContain('Load summary');
});

it('renders fallback flatbed pallets in the shared load summary', function () {
    $item = [
        'sku' => 'UV1212-M',
        'name' => 'Monticello Urn Vault',
        'quantity' => 4,
        'fill_load' => false,
        'fill_resolved' => true,
        'handling_method' => 'pallet',
        'rack_requirement' => 'standard',
        'pallet_equivalent' => 1.0,
        'total_weight_lbs' => 416.0,
    ];
    $result = [
        'summary' => [
            'product_units' => 4,
            'oversized_rack_spots' => 0,
            'pallets' => 1,
            'known_weight_lbs' => 416.0,
            'maximum_product_weight_lbs' => 38500.0,
            'remaining_product_weight_lbs' => 38084.0,
            'overweight_by_lbs' => 0.0,
            'is_overweight' => false,
        ],
        'stops' => [[
            'sequence' => 1,
            'order_id' => 1,
            'order_number' => 'ORD-00001',
            'location_name' => 'Test Cemetery',
            'summary' => ['known_weight_lbs' => 416.0],
            'items' => [$item],
        ]],
        'warnings' => [],
        'vehicle_configuration' => [
            'name' => 'Rack trailer — forklift onboard',
            'rack_spot_count' => 8,
            'flatbed_pallet_capacity' => 4,
            'piggyback_forklift_onboard' => true,
        ],
        'ready_for_automatic_placement' => true,
    ];
    $diagram = [
        'available' => true,
        'message' => null,
        'racks' => [],
        'legend' => [[
            'code' => 'UVM',
            'sku' => 'UV1212-M',
            'name' => 'Monticello Urn Vault',
            'unit_weight_lbs' => 104.0,
            'handling_method' => 'pallet',
            'rack_requirement' => 'standard',
            'units_per_rack_position' => 1,
        ], [
            'code' => 'G6',
            'sku' => 'G3086-6',
            'name' => 'Single Garden Crypt',
            'unit_weight_lbs' => 1750.0,
            'handling_method' => 'individual',
            'rack_requirement' => 'standard',
            'units_per_rack_position' => 1,
        ]],
        'unplaced' => [],
        'used_rack_spots' => 8,
        'rack_spot_count' => 8,
        'flatbed_pallet_capacity' => 4,
        'flatbed_pallets_used' => 1,
        'flatbed_pallets' => [[
            'code' => '4×UVM',
            'name' => 'Monticello Urn Vault',
            'stop_sequence' => 1,
            'total_weight_lbs' => 416.0,
        ]],
    ];

    $html = view('filament.resources.trip-resource.load-summary', [
        'result' => $result,
        'diagram' => $diagram,
        'fillAllocations' => [],
    ])->render();

    expect($html)->toContain('Strapped flatbed fallback')
        ->and($html)->toContain('4×UVM')
        ->and($html)->toContain('Strap to deck')
        ->and($html)->toContain('P1')
        ->and($html)->toContain('1,750 lb each')
        ->and($html)->not->toContain('104 lb each')
        ->and($html)->toContain('Compact cab-over truck tractor')
        ->and($html)->toContain('Piggyback forklift suspended from rear of trailer');
});
