<?php

use App\Filament\Actions\TripLoadSummaryAction;
use App\Filament\Resources\OrderResource\Pages\DeliveryCalendar;
use App\Livewire\OrderModal;
use App\Models\Order;
use App\Models\Trip;
use App\Models\User;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;

it('requires both a trip and the view load summary permission', function () {
    $unassignedOrder = new Order;
    $assignedOrder = (new Order)->forceFill(['trip_id' => 42]);

    Gate::shouldReceive('allows')
        ->twice()
        ->with('view load summary')
        ->andReturn(false, true);

    expect(TripLoadSummaryAction::make()->record($unassignedOrder)->isVisible())->toBeFalse()
        ->and(TripLoadSummaryAction::make()->record($assignedOrder)->isVisible())->toBeFalse()
        ->and(TripLoadSummaryAction::make()->record($assignedOrder)->isVisible())->toBeTrue();
});

it('uses the same permission for the calendar order modal', function () {
    $modal = new OrderModal;
    $modal->order = (new Order)->forceFill(['trip_id' => 42]);

    Gate::shouldReceive('allows')
        ->twice()
        ->with('view load summary')
        ->andReturn(false, true);

    expect($modal->canViewLoadSummary())->toBeFalse()
        ->and($modal->canViewLoadSummary())->toBeTrue();
});

it('shows the load summary in the saved trip edit modal with the same permission', function () {
    $page = new class extends DeliveryCalendar
    {
        public function tripEditorActionForTest(): Action
        {
            return collect($this->getHeaderActions())
                ->first(fn (Action $action): bool => $action->getName() === 'createSplitLoad');
        }
    };
    $loadSummaryAction = $page->tripEditorActionForTest()
        ->livewire($page)
        ->getExtraModalFooterActions()['viewTripLoadSummary'];

    Gate::shouldReceive('allows')
        ->twice()
        ->with('view load summary')
        ->andReturn(false, true);

    expect($loadSummaryAction->isVisible())->toBeFalse();

    $page->editingTripId = 42;

    expect($loadSummaryAction->isVisible())->toBeFalse()
        ->and($loadSummaryAction->isVisible())->toBeTrue();
});

it('shows the team schedule load summary trigger only with permission', function () {
    $user = new class extends User
    {
        public bool $mayViewLoadSummary = false;

        public function can($abilities, $arguments = []): bool
        {
            return $abilities === 'view load summary' && $this->mayViewLoadSummary;
        }
    };
    auth()->setUser($user);
    $trip = (new Trip)->forceFill(['id' => 42]);

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
        ->and($html)->toContain('P1');
});
