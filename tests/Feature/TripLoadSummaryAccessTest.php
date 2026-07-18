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
