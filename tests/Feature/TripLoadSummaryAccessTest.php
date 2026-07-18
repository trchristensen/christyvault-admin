<?php

use App\Filament\Actions\TripLoadSummaryAction;
use App\Livewire\OrderModal;
use App\Models\Order;
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
