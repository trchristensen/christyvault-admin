<?php

namespace App\Filament\Resources\OrderResource\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Resources\OrderResource;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Order;
use App\Models\Product;
use Carbon\Carbon;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;
use Saade\FilamentFullCalendar\Actions;
use Illuminate\Support\Str;
use Saade\FilamentFullCalendar\Actions\ViewAction;
use Filament\Forms\Get;
use App\Filament\Resources\Traits\HasOrderForm;
use App\Filament\Resources\Traits\HasTripForm;
use App\Models\Trip;
use Saade\FilamentFullCalendar\Actions\CreateAction;
use Saade\FilamentFullCalendar\Actions\EditAction;
use Illuminate\Database\Eloquent\Builder;

class CalendarWidget extends FullCalendarWidget
{
    use HasOrderForm;
    use HasTripForm;


    public Model | string | null $model = Order::class;
    public ?Model $event = null;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected bool $selectable = true;
    protected bool $editable = true;

    public ?string $selectedDate = null;
    public ?string $selectedType = null;

    public function getViewData(): array
    {
        return [
            'pageTitle' => 'Delivery Calendar',
        ];
    }



    protected function getFormModel(): Model|string|null
    {
        if ($this->event instanceof Trip) {
            return $this->record ?? Trip::class;
        }
        return $this->record ?? Order::class;
    }

    public function getFormSchema(): array
    {
        // Initialize $record if it's not set
        if (!isset($this->record)) {
            $this->record = null;
        }


        if ($this->record instanceof Trip) {
            return static::getTripFormSchema();
        } else if ($this->record instanceof Order) {
            return static::getOrderFormSchema();
        }
        return [];
    }


    public function onEventClick(array $event): void
    {
        $uuid = $event['extendedProps']['uuid'];
        $type = $event['extendedProps']['type'];

        if ($type === 'trip') {
            $this->record = Trip::with(['orders.customer', 'driver'])->where('uuid', $uuid)->first();
        } else {
            $this->record = Order::with(['customer', 'orderProducts.product'])->where('uuid', $uuid)->first();
        }

        $this->mountAction('view');
    }


    public function onEventDrop(array $event, array $oldEvent, array $relatedEvents, array $delta, ?array $oldResource, ?array $newResource): bool
    {
        // Extract the numeric ID from the prefixed string
        $id = $event['id'];
        $uuid = $event['extendedProps']['uuid'];

        if (str_starts_with($id, 'order_')) {

            $order = Order::where('uuid', $uuid)->first();
            if (!$order) {

                return false;
            }


            try {
                $newDate = Carbon::parse($event['start'])->toDateString();
                $order->update([
                    'assigned_delivery_date' => $newDate,
                ]);
                $this->refreshRecords();
                return true;
            } catch (\Exception $e) {
                return false;
            }
        } else if (str_starts_with($id, 'trip_')) {

            $trip = Trip::where('uuid', $uuid)->first();


            if (!$trip) {
                return false;
            }

            try {
                $newDate = Carbon::parse($event['start'])->toDateString();
                $trip->update([
                    'scheduled_date' => $newDate,
                ]);
                $this->refreshRecords();

                return true;
            } catch (\Exception $e) {
                return false;
            }
        }
        dd($event, $oldEvent, $relatedEvents, $delta, $oldResource, $newResource);
        return false;
    }

    public function eventDidMount(): string
    {
        return <<<'JS'
    function({ event, el }) {
        const eventMainEl = el.querySelector('.fc-event-main');

        if (event.extendedProps.type === 'trip') {
            // Trip content
            const content = document.createElement('div');
            content.innerHTML = `
                <div class="trip-title">${event.title}</div>
                ${event.extendedProps.orders.map(order => `
                    <div class="order-container">
                        <div class="order-title">${order.title}</div>
                        <div class="order-status">Status: ${order.status}</div>
                        ${order.products.map(p => `
                            <div class="product-item">
                                ${p.fill_load ? '*' : p.quantity} × ${p.sku} ${p.fill_load ? '(fill load)' : ''}
                            </div>
                        `).join('')}
                    </div>
                `).join('')}
            `;
            eventMainEl.replaceChildren(content);
        } else {
            // Order content
            const content = document.createElement('div');
            content.innerHTML = `
                <div class="order-title">${event.title}</div>
                <div class="order-status">Status: ${event.extendedProps.status}</div>
                ${event.extendedProps.products.map(p => `
                    <div class="product-item">
                        ${p.fill_load ? '*' : p.quantity} × ${p.sku} ${p.fill_load ? '(fill load)' : ''}
                    </div>
                `).join('')}
            `;
            eventMainEl.replaceChildren(content);
        }
    }
    JS;
    }

    public function refreshCalendar(): void
    {
        $this->dispatch('filament-fullcalendar--refresh');
    }

    protected function modalActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Create New')
                ->modalHeading('Create New')
                ->modalWidth('2xl')
                ->record(fn() => $this->record = null)
                ->form([
                    Select::make('type')
                        ->label('What would you like to create?')
                        ->options([
                            'order' => 'New Order',
                            'trip' => 'New Trip',
                        ])
                        ->required()
                        ->live(),

                    // Order Form (shown when type = order)
                    ...collect(static::getOrderFormSchema())->map(
                        fn($field) =>
                        $field->visible(fn(Get $get) => $get('type') === 'order')
                    ),

                    // Trip Form (shown when type = trip)
                    Section::make('Trip Details')
                        ->schema([
                            DatePicker::make('scheduled_date')
                                ->required()
                                ->native(false),
                            Select::make('driver_id')
                                ->relationship('driver', 'name')
                                ->required(),
                            TextInput::make('notes')
                                ->maxLength(255),
                        ])
                        ->visible(fn(Get $get) => $get('type') === 'trip')
                        ->columns(2),

                    // Add Orders Section
                    Section::make('Orders')
                        ->schema([
                            Select::make('orders')
                                ->multiple()
                                ->options(
                                    Order::query()
                                        ->whereNull('trip_id')
                                        ->whereNotIn('status', ['completed', 'cancelled'])
                                        ->get()
                                        ->mapWithKeys(fn(Order $order) => [
                                            $order->id => "{$order->order_number} - {$order->customer?->name}"
                                        ])
                                )
                                ->preload()
                                ->searchable()
                        ])
                        ->visible(fn(Get $get) => $get('type') === 'trip'),
                ])
                ->action(function (array $data) {
                    if ($data['type'] === 'trip') {
                        // Create the trip first
                        $trip = Trip::create([
                            'trip_number' => $data['trip_number'] ?? $this->generateTripNumber(),
                            'scheduled_date' => $data['scheduled_date'],
                            'driver_id' => $data['driver_id'],
                            'notes' => $data['notes'] ?? null,
                            'status' => 'pending',
                        ]);

                        // Update the selected orders with the new trip_id
                        if (!empty($data['orders'])) {
                            Order::whereIn('id', $data['orders'])->update([
                                'trip_id' => $trip->id,
                                'assigned_delivery_date' => $data['scheduled_date']
                            ]);
                        }
                    } else {
                        Order::create([
                            ...collect($data)->except('type')->toArray(),
                            'order_date' => now(),
                            'status' => OrderStatus::PENDING->value,
                        ]);
                    }
                    $this->refreshRecords();
                }),
        ];
    }


    protected function viewAction(): \Filament\Actions\Action
    {
        return Actions\EditAction::make();
    }

    protected function getEventColor(Order $order): string
    {
        // Cast the string status to enum instance
        $status = OrderStatus::from($order->status);

        // Call color() on the enum instance
        return $status->color();
    }



    public function refreshRecords(): void
    {
        $this->dispatch('filament-fullcalendar--refresh');
    }

    public function fetchEvents(array $fetchInfo): array
    {
        // Fetch Trips
        $tripEvents = Trip::with(['orders.customer', 'orders.orderProducts.product', 'driver'])
            ->whereDate('scheduled_date', '>=', $fetchInfo['start'])
            ->whereDate('scheduled_date', '<=', $fetchInfo['end'])
            ->get()
            ->map(function (Trip $trip) {
                return [
                    'id' => 'trip_' . $trip->id,
                    'title' => "{$trip->trip_number}\n{$trip->driver?->name}",
                    'start' => $trip->scheduled_date->format('Y-m-d'),
                    'allDay' => true,
                    'backgroundColor' => '#2563EB',
                    'borderColor' => '#1E40AF',
                    'classNames' => ['trip-event'],
                    'extendedProps' => [
                        'type' => 'trip',
                        'uuid' => $trip->uuid,
                        'orders' => $trip->orders->map(fn($order) => [
                            'id' => $order->id,
                            'title' => $order->customer?->name ?? $order->order_number,
                            'status' => Str::headline($order->status),
                            'products' => $order->orderProducts->map(fn($op) => [
                                'quantity' => $op->quantity,
                                'sku' => $op->product->sku,
                                'fill_load' => $op->fill_load
                            ])->toArray(),
                        ])->toArray()
                    ],
                ];
            });

        // Fetch standalone Orders
        $orderEvents = Order::whereNull('trip_id')
            ->with(['customer', 'orderProducts.product'])
            ->whereDate('requested_delivery_date', '>=', $fetchInfo['start'])
            ->whereDate('requested_delivery_date', '<=', $fetchInfo['end'])
            ->get()
            ->map(function (Order $order) {
                $isLocked = in_array($order->status, ['in_progress', 'completed', 'delivered']);
                return [
                    'id' => 'order_' . $order->id,
                    'title' => $order->customer?->name ?? $order->order_number,
                    'start' => $order->assigned_delivery_date?->format('Y-m-d') ?? $order->requested_delivery_date->format('Y-m-d'),
                    'allDay' => true,
                    'backgroundColor' => $order->assigned_delivery_date ? $this->getEventColor($order) : 'grey',
                    'borderColor' => 'transparent',
                    'editable' => !$isLocked,
                    'extendedProps' => [
                        'type' => 'order',
                        'uuid' => $order->uuid,
                        'customerName' => $order->customer?->name,
                        'requestedDate' => $order->requested_delivery_date->format('m/d'),
                        'status' => Str::headline($order->status),
                        'isLocked' => $isLocked,
                        'products' => $order->orderProducts->map(fn($op) => [
                            'quantity' => $op->quantity,
                            'sku' => $op->product->sku,
                            'fill_load' => $op->fill_load
                        ])->toArray(),
                    ],
                ];
            });

        return $tripEvents->concat($orderEvents)->toArray();
    }



    // config
    public function config(): array
    {
        $today = now();
        $startDate = $today->startOfMonth();
        $endDate = $today->copy()->addMonths(2)->endOfMonth();

        return [
            // 'initialView' => 'dayGridMonth',
            'weekends' => false,
            'initialView' => 'dayGridMonth',
            'multiMonth' => [
                'months' => 3, // Show only 3 months at a time
                'startMonth' => $today->month,
                'startYear' => $today->year,
            ],
            'validRange' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'dayMaxEvents' => false, // This will force all events to be shown
            'multiMonthMaxColumns' => 1,
            'selectable' => true,
            'editable' => true,
            'headerToolbar' => [
                'left' => 'prev,next today',
                'center' => 'title',
                'right' => 'dayGridMonth,timeGridWeek,timeGridDay, multiMonthYear',
            ],

        ];
    }

    protected function getActions(): array
    {
        return [
            ViewAction::make()
                ->modalWidth('2xl')
                ->form(fn() => $this->getFormSchema()),
            EditAction::make()
                ->modalWidth('2xl')
                ->form(fn() => $this->getFormSchema())
                ->action(function (array $data) {
                    $this->event->update($data);
                    $this->refreshRecords();
                }),
        ];
    }

    public function onSelectDate(array $info): void
    {
        $this->selectedDate = $info['date'];
        $this->record = null; // Ensure record is null for new creation
        $this->mountAction('create');
    }

    protected function generateTripNumber(): string
    {
        $lastTrip = Trip::orderBy('id', 'desc')->first();
        $lastNumber = $lastTrip ? (int) str_replace('TRIP-', '', $lastTrip->trip_number) : 0;
        $newNumber = $lastNumber + 1;

        return 'TRIP-' . str_pad($newNumber, 5, '0', STR_PAD_LEFT);
    }
}
