<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Enums\OrderStatus;
use App\Filament\Actions\TripLoadSummaryAction;
use App\Filament\Resources\OrderResource;
use App\Filament\Resources\Traits\HasOrderForm;
use App\Models\Employee;
use App\Models\Order;
use App\Models\Trip;
use App\Models\VehicleConfiguration;
use App\Services\DeliveryCalendarAvailability;
use App\Services\LoadPlanning\TripLoadPlanService;
use App\Services\SplitLoadService;
use App\Services\TripVehicleConfigurationResolver;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Throwable;

class DeliveryCalendar extends Page
{
    use HasOrderForm;

    protected static string $resource = OrderResource::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|\UnitEnum|null $navigationGroup = 'Delivery Management';

    protected static ?string $navigationLabel = 'Delivery Calendar';

    // protected static ?int $navigationSort = ; // Adjust this number to change the order in the sidebar
    protected static ?string $slug = 'delivery-calendar';

    protected string $view = 'filament.resources.order-resource.pages.delivery-calendar';

    protected $listeners = [
        'openOrderModal',
        'editDeliveryTrip' => 'openSplitLoadModal',
    ];

    public ?string $selectedDate = null;

    public ?int $editingTripId = null;

    public ?int $editingTripStopCount = null;

    public function getTitle(): string
    {
        return 'Delivery Calendar';
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    protected function tripForLoadSummary(int $tripId): Trip
    {
        return Trip::query()->findOrFail($tripId);
    }

    protected function authorizedTripForLoadSummary(int $tripId): Trip
    {
        $trip = $this->tripForLoadSummary($tripId);

        abort_unless($trip->loadSummaryIsVisibleTo(auth()->user()), 403);

        return $trip;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createSplitLoad')
                ->label('Create delivery trip')
                ->icon('heroicon-o-link')
                ->color('info')
                ->modalHeading(fn (): string => match (true) {
                    $this->editingTripStopCount === 1 => 'Edit delivery',
                    $this->editingTripId !== null => 'Edit delivery trip',
                    default => 'Plan a delivery trip',
                })
                ->modalDescription(fn (): string => $this->editingTripStopCount === 1
                    ? 'Update the delivery date, driver, or vehicle configuration. Add another stop if this order will share the trip.'
                    : 'Add two or more stops, then drag the rows into the order the driver should visit them.')
                ->modalSubmitActionLabel(fn (): string => $this->editingTripId ? 'Save trip' : 'Create trip')
                ->modalWidth('4xl')
                ->extraModalFooterActions([
                    ActionGroup::make([
                        Action::make('viewTripLoadSummary')
                            ->label('Load summary')
                            ->hiddenLabel()
                            ->icon(TripLoadSummaryAction::ICON)
                            ->color('gray')
                            ->tooltip('Load summary')
                            ->visible(fn (): bool => $this->editingTripId !== null
                                && $this->tripForLoadSummary($this->editingTripId)->loadSummaryIsVisibleTo(auth()->user()))
                            ->modalHeading(function (): string {
                                $trip = $this->authorizedTripForLoadSummary($this->editingTripId);

                                return "Load summary — {$trip->trip_number}";
                            })
                            ->modalDescription('This summary uses the last saved trip. Save trip changes before relying on an updated load plan.')
                            ->modalContent(function () {
                                $trip = $this->authorizedTripForLoadSummary($this->editingTripId);
                                $plan = app(TripLoadPlanService::class)->forTrip($trip);

                                return view('filament.resources.trip-resource.load-summary', [
                                    'result' => $plan['demand']->toArray(),
                                    'diagram' => $plan['diagram'],
                                    'fillAllocations' => $plan['fill_allocations'],
                                    'printUrl' => route('trips.load-summary.print', [
                                        'trip' => $trip,
                                        'print' => 1,
                                    ]),
                                ]);
                            })
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Back to trip')
                            ->modalWidth('7xl'),
                        ActionGroup::make([
                            Action::make('separateOrders')
                                ->label('Separate orders')
                                ->icon('heroicon-o-arrows-pointing-out')
                                ->color('warning')
                                ->requiresConfirmation()
                                ->modalHeading('Separate these orders?')
                                ->modalDescription('Each order will become its own delivery again. The split-load history is retained, and no orders, photos, signatures, or activity records are deleted.')
                                ->modalSubmitActionLabel('Separate orders')
                                ->action(function (Action $action): void {
                                    $tripId = $this->editingTripId;

                                    if (! $tripId) {
                                        $action->halt();
                                    }

                                    try {
                                        app(SplitLoadService::class)->dissolve(Trip::findOrFail($tripId));

                                        Notification::make()
                                            ->title('Orders separated')
                                            ->body('Each order is now its own delivery again.')
                                            ->success()
                                            ->send();

                                        $this->editingTripId = null;
                                        $this->dispatch('refresh-calendar');
                                    } catch (Throwable $exception) {
                                        report($exception);

                                        Notification::make()
                                            ->title('Could not separate these orders')
                                            ->body($exception->getMessage())
                                            ->danger()
                                            ->send();

                                        $action->halt();
                                    }
                                })
                                ->cancelParentActions(),
                        ])
                            ->label('More trip actions')
                            ->hiddenLabel()
                            ->icon('heroicon-o-ellipsis-horizontal')
                            ->color('gray')
                            ->tooltip('More trip actions')
                            ->visible(fn (): bool => $this->editingTripId !== null
                                && ($this->editingTripStopCount ?? 0) > 1),
                    ])
                        ->buttonGroup(),
                ])
                ->schema([
                    Grid::make(3)
                        ->schema([
                            DatePicker::make('scheduled_date')
                                ->label('Delivery date')
                                ->native(false)
                                ->required(),
                            Select::make('driver_id')
                                ->label('Driver')
                                ->options(fn (): array => Employee::query()
                                    ->whereHas('positions', fn ($query) => $query->where('name', 'driver'))
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all())
                                ->searchable()
                                ->preload()
                                ->placeholder('Assign later'),
                            Select::make('vehicle_configuration_id')
                                ->label('Vehicle Configuration')
                                ->options(fn (): array => VehicleConfiguration::query()
                                    ->where('is_active', true)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all())
                                ->searchable()
                                ->preload()
                                ->required(),
                        ]),
                    Repeater::make('stops')
                        ->label('Stops')
                        ->schema([
                            Select::make('order_id')
                                ->label('Order')
                                ->options(fn (): array => $this->splitLoadOrderOptions($this->editingTripId))
                                ->searchable()
                                ->preload()
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                ->live()
                                ->afterStateUpdated(function (Get $get, Set $set): void {
                                    $orderIds = collect($get('../../stops') ?? [])
                                        ->pluck('order_id')
                                        ->filter()
                                        ->values();

                                    $set(
                                        '../../vehicle_configuration_id',
                                        app(TripVehicleConfigurationResolver::class)->defaultIdForOrderIds($orderIds),
                                    );
                                })
                                ->required(),
                            Textarea::make('delivery_notes')
                                ->label('Stop notes')
                                ->rows(2)
                                ->placeholder('Optional delivery notes for this stop'),
                        ])
                        ->columns(2)
                        ->minItems(fn (): int => $this->editingTripStopCount === 1 ? 1 : 2)
                        ->defaultItems(2)
                        ->reorderable()
                        ->reorderableWithButtons()
                        ->addActionLabel('Add another stop')
                        ->columnSpanFull(),
                ])
                ->fillForm(function (Action $action): array {
                    $arguments = $action->getArguments();
                    $this->editingTripId = isset($arguments['tripId'])
                        ? (int) $arguments['tripId']
                        : null;
                    $this->editingTripStopCount = null;

                    if ($this->editingTripId) {
                        $trip = Trip::query()
                            ->with(['orders' => fn ($query) => $query->orderBy('stop_number')])
                            ->findOrFail($this->editingTripId);
                        $this->editingTripStopCount = $trip->orders->count();

                        return [
                            'scheduled_date' => $trip->scheduled_date?->toDateString(),
                            'driver_id' => $trip->driver_id,
                            'vehicle_configuration_id' => $trip->vehicle_configuration_id
                                ?? app(TripVehicleConfigurationResolver::class)
                                    ->defaultIdForOrderIds($trip->orders->modelKeys()),
                            'stops' => $trip->orders->map(fn (Order $order): array => [
                                'order_id' => $order->getKey(),
                                'delivery_notes' => $order->delivery_notes,
                            ])->all(),
                        ];
                    }

                    $firstOrder = Order::find($arguments['firstOrder'] ?? null);
                    $secondOrder = Order::find($arguments['secondOrder'] ?? null);
                    $orders = collect([$firstOrder, $secondOrder])->filter()->values();
                    $drivers = $orders->pluck('driver_id')->filter()->unique();

                    return [
                        'scheduled_date' => $firstOrder?->assigned_delivery_date?->toDateString()
                            ?? $secondOrder?->assigned_delivery_date?->toDateString(),
                        'driver_id' => $drivers->count() === 1 ? $drivers->first() : null,
                        'vehicle_configuration_id' => app(TripVehicleConfigurationResolver::class)
                            ->defaultIdForOrderIds($orders->pluck('id')),
                        'stops' => $orders->isNotEmpty()
                            ? $orders->map(fn (Order $order): array => [
                                'order_id' => $order->getKey(),
                                'delivery_notes' => $order->delivery_notes,
                            ])->all()
                            : [[], []],
                    ];
                })
                ->action(function (array $data): void {
                    $service = app(SplitLoadService::class);
                    $isEditing = $this->editingTripId !== null;
                    $trip = $isEditing
                        ? $service->updateTrip(
                            Trip::findOrFail($this->editingTripId),
                            $data['stops'],
                            $data['scheduled_date'],
                            $data['driver_id'] ?? null,
                            $data['vehicle_configuration_id'] ?? null,
                        )
                        : $service->createTrip(
                            $data['stops'],
                            $data['scheduled_date'],
                            $data['driver_id'] ?? null,
                            $data['vehicle_configuration_id'] ?? null,
                        );

                    Notification::make()
                        ->title($isEditing ? 'Delivery trip updated' : 'Delivery trip created')
                        ->body("{$trip->trip_number} now has {$trip->orders->count()} stops.")
                        ->success()
                        ->send();

                    $this->editingTripId = null;
                    $this->editingTripStopCount = null;
                    $this->dispatch('refresh-calendar');
                }),
            CreateAction::make('createOrder')
                ->label('Create Order')
                ->model(Order::class)
                ->schema(fn (Schema $schema) => $schema->components(static::getOrderFormSchema($this->selectedDate)))
                ->action(function (array $data) {
                    app(DeliveryCalendarAvailability::class)->validateDate(
                        $data['assigned_delivery_date'] ?? null,
                        'assigned_delivery_date'
                    );

                    $order = Order::create($data);

                    // Handle order products
                    if (isset($data['orderProducts'])) {
                        foreach ($data['orderProducts'] as $productData) {
                            $order->orderProducts()->create($productData);
                        }
                    }

                    $order->location?->updateOrderAnalytics();

                    Notification::make()
                        ->title('Order created successfully')
                        ->success()
                        ->send();

                    // Refresh the calendar to show the new order
                    $this->dispatch('refresh-calendar');

                    return $order;
                })
                ->modalWidth('7xl'),
            Action::make('Print Calendar')
                ->url(route('delivery-calendar.print'))
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->openUrlInNewTab(),
        ];
    }

    public function getViewData(): array
    {
        return [
            'unassignedOrders' => Order::whereNull('assigned_delivery_date')
                ->whereIn('status', [
                    OrderStatus::PENDING->value,
                    OrderStatus::CONFIRMED->value,
                    OrderStatus::WILL_CALL->value,
                    OrderStatus::IN_PRODUCTION->value,
                    OrderStatus::PREBURY->value,
                    OrderStatus::READY_FOR_DELIVERY->value,
                    OrderStatus::TRANSFER->value,
                ])
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get(),
        ];
    }

    public function openOrderModal($orderId)
    {
        logger('DeliveryCalendar: dispatching showOrderModal event with ID: '.$orderId);
        $this->dispatch('showOrderModal', $orderId);
    }

    public function openCreateOrderModal($date)
    {
        if (app(DeliveryCalendarAvailability::class)->isBlocked($date)) {
            Notification::make()
                ->title('Date blocked')
                ->body(app(DeliveryCalendarAvailability::class)->blockingReason($date) ?? 'This day is blocked for delivery.')
                ->warning()
                ->send();

            return;
        }

        $this->selectedDate = $date;

        // Mount the createOrder action from header actions
        $this->mountAction('createOrder');
    }

    public function openCreateOrderModalFromHeader()
    {
        $this->selectedDate = null;

        // Mount the createOrder action from header actions
        $this->mountAction('createOrder');
    }

    public function requestSplitLoad(int $draggedOrderId, int $targetOrderId): void
    {
        if ($draggedOrderId === $targetOrderId) {
            return;
        }

        $this->mountAction('createSplitLoad', [
            'firstOrder' => $targetOrderId,
            'secondOrder' => $draggedOrderId,
        ]);
    }

    public function openSplitLoadModal(int $tripId): void
    {
        $this->mountAction('createSplitLoad', ['tripId' => $tripId]);
    }

    public function reverseSplitLoad(int $tripId): void
    {
        try {
            app(SplitLoadService::class)->reverse(Trip::findOrFail($tripId));

            Notification::make()
                ->title('Stop order reversed')
                ->success()
                ->send();

            $this->dispatch('refresh-calendar');
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Could not reverse this delivery trip')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function splitLoadOrderOptions(?int $editingTripId = null): array
    {
        return Order::query()
            ->where(function ($query) use ($editingTripId): void {
                $query->whereNull('trip_id');

                if ($editingTripId) {
                    $query->orWhere('trip_id', $editingTripId);
                }

                $query->orWhereHas('trip', fn ($tripQuery) => $tripQuery->has('orders', '=', 1));
            })
            ->whereNotNull('assigned_delivery_date')
            ->where(function ($query) use ($editingTripId): void {
                $query->whereNotIn('status', [
                    OrderStatus::DELIVERED->value,
                    OrderStatus::CANCELLED->value,
                    OrderStatus::COMPLETED->value,
                ]);

                if ($editingTripId) {
                    $query->orWhere('trip_id', $editingTripId);
                }
            })
            ->with('location')
            ->orderByDesc('assigned_delivery_date')
            ->limit(250)
            ->get()
            ->mapWithKeys(fn (Order $order): array => [
                $order->getKey() => sprintf(
                    '%s — %s — %s',
                    $order->order_number,
                    $order->location?->name ?? 'Unknown location',
                    $order->assigned_delivery_date?->format('M j, Y'),
                ),
            ])
            ->all();
    }
}
