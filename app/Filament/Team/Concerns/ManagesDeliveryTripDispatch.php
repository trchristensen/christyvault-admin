<?php

namespace App\Filament\Team\Concerns;

use App\Models\Employee;
use App\Models\Trip;
use App\Services\LoadPlanning\TripLoadPlanService;
use App\Services\SplitLoadService;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;

trait ManagesDeliveryTripDispatch
{
    public function viewDeliveryTripLoadSummaryAction(): Action
    {
        return Action::make('viewDeliveryTripLoadSummary')
            ->authorize('view load summary')
            ->modalHeading(function (Action $action): string {
                $trip = $this->deliveryTripForLoadSummary((int) ($action->getArguments()['trip'] ?? 0));

                return "Load summary — {$trip->trip_number}";
            })
            ->modalContent(function (Action $action) {
                $trip = $this->deliveryTripForLoadSummary((int) ($action->getArguments()['trip'] ?? 0));
                $plan = app(TripLoadPlanService::class)->forTrip($trip);

                return view('filament.resources.trip-resource.load-summary', [
                    'result' => $plan['demand']->toArray(),
                    'diagram' => $plan['diagram'],
                    'fillAllocations' => $plan['fill_allocations'],
                ]);
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalWidth('7xl');
    }

    public function manageDeliveryTripDispatchAction(): Action
    {
        return Action::make('manageDeliveryTripDispatch')
            ->authorize('manage delivery trip dispatch')
            ->modalHeading('Manage delivery')
            ->modalDescription('Assign the driver and arrange multi-stop deliveries. Saving confirms the stop order for the team.')
            ->modalSubmitActionLabel('Save and confirm')
            ->modalWidth('2xl')
            ->schema([
                Select::make('driver_id')
                    ->label('Driver')
                    ->options(fn (): array => Employee::query()
                        ->where('is_active', true)
                        ->whereHas('positions', fn ($query) => $query->where('name', 'driver'))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->placeholder('Driver unassigned'),
                Repeater::make('stops')
                    ->label(fn (Repeater $component): string => collect($component->getRawState())->count() > 1
                        ? 'Stop order'
                        : 'Destination')
                    ->schema([
                        Hidden::make('order_id'),
                        TextInput::make('stop_label')
                            ->label('Stop')
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(fn (Repeater $component): bool => collect($component->getRawState())->count() > 1)
                    ->reorderableWithButtons(fn (Repeater $component): bool => collect($component->getRawState())->count() > 1)
                    ->minItems(1)
                    ->columnSpanFull(),
            ])
            ->fillForm(function (Action $action): array {
                $trip = $this->deliveryTripForDispatch((int) ($action->getArguments()['trip'] ?? 0));

                return [
                    'driver_id' => $trip->driver_id,
                    'stops' => $trip->orderedDeliveryOrders()->map(fn ($order): array => [
                        'order_id' => $order->getKey(),
                        'stop_label' => collect([
                            $order->order_number,
                            $order->location?->name ?? 'Unknown location',
                            $order->location
                                ? collect([$order->location->city, $order->location->state])->filter()->join(', ')
                                : null,
                        ])->filter()->join(' — '),
                    ])->all(),
                ];
            })
            ->action(function (Action $action, array $data): void {
                $trip = $this->deliveryTripForDispatch((int) ($action->getArguments()['trip'] ?? 0));

                $trip = app(SplitLoadService::class)->updateDispatchPlan(
                    $trip,
                    collect($data['stops'] ?? [])->pluck('order_id')->all(),
                    isset($data['driver_id']) ? (int) $data['driver_id'] : null,
                );

                $this->refreshDeliveryTripDispatchView();

                Notification::make()
                    ->title($trip->deliveryStopCount() > 1 ? 'Stop order confirmed' : 'Delivery plan updated')
                    ->body("{$trip->trip_number} is assigned to ".($trip->driver?->name ?? 'no driver').'.')
                    ->success()
                    ->send();
            });
    }

    protected function deliveryTripForDispatch(int $tripId): Trip
    {
        $trip = Trip::query()
            ->with([
                'driver',
                'orders' => fn ($query) => $query->with('location')->orderBy('stop_number'),
                'stops.order.location',
            ])
            ->findOrFail($tripId);

        if (! (auth()->user()?->can('manage delivery trip dispatch') ?? false)
            || ! $this->deliveryTripDispatchIsInScope($trip)) {
            throw new AuthorizationException('You cannot manage this delivery trip.');
        }

        return $trip;
    }

    protected function deliveryTripForLoadSummary(int $tripId): Trip
    {
        $trip = Trip::query()->findOrFail($tripId);

        if (! $trip->loadSummaryIsVisibleTo(auth()->user())
            || ! $this->deliveryTripDispatchIsInScope($trip)) {
            throw new AuthorizationException('You cannot view this delivery trip load summary.');
        }

        return $trip;
    }

    abstract protected function deliveryTripDispatchIsInScope(Trip $trip): bool;

    abstract protected function refreshDeliveryTripDispatchView(): void;
}
