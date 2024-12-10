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
use Filament\Actions\Action;
use Saade\FilamentFullCalendar\Actions\CreateAction;
use Saade\FilamentFullCalendar\Actions\EditAction;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Hidden;
use Filament\Support\Colors\Color;
use Filament\Notifications\Notification;
use Filament\Facades\Filament;
use Filament\Facades\Filament\MaxWidth;

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

    protected $listeners = [
        'date-selected' => 'handleDateSelected',
        'calendar-order-clicked' => 'handleOrderClick'
    ];

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
            return static::getOrderFormSchema($this->selectedDate);
        }
        return [];
    }

    public function onEventClick(array $event): void
    {
        // Check if we clicked on an order within a trip
        if (isset($event['jsEvent']['target']['dataset']['orderId'])) {
            $orderId = $event['jsEvent']['target']['dataset']['orderId'];
            Log::info('Clicked on order:', ['orderId' => $orderId]);

            $this->record = Order::with(['customer', 'orderProducts.product'])->find($orderId);
            $this->mountAction('view');
            return;
        }

        // Default trip/standalone order handling
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

        return false;
    }

    public function eventDidMount(): string
    {
        return <<<'JS'
    function({ event, el }) {
        const eventMainEl = el.querySelector('.fc-event-main');

        const chevronSvg = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="transition-transform duration-200 transform size-4">
            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
        </svg>`;

        if (event.extendedProps.type === 'trip') {
            const content = document.createElement('div');
            content.innerHTML = `
            <div class="p-2 pb-0 rounded-t-lg bg-gray-50">
                <div class="trip-title">
                    <div class="flex flex-wrap items-center justify-between">
                        ${event.extendedProps.driver_name ? `<span class="driver-name">${event.extendedProps.driver_name}</span>` : ''}
                        ${event.title ? `<span class="trip-number !text-sm text-gray-600 dark:text-gray-400 !font-medium">${event.title}</span>` : ''}
                    </div>
                </div>
                ${event.extendedProps.status ? `<div class="trip-status">${event.extendedProps.status}</div>` : ''}
                </div>
                ${(event.extendedProps.orders || []).map(order => `
                    <div class="order-container status-${(order.status || '').toLowerCase()}" data-order-id="${order.id || ''}" onclick="event.stopPropagation();">
                        ${order.title ? `<div class="order-title">${order.title}</div>` : ''}
                        ${(order.extendedProps?.location_line1 || order.extendedProps?.location_line2) ? `
                            <div class="order-address">
                                ${order.extendedProps?.location_line2 ? `<div>${order.extendedProps.location_line2}</div>` : ''}
                            </div>
                        ` : ''}

                        ${order.requested_delivery_date ? `<div class="order-requested-delivery-date"><span>Requested: </span> ${order.requested_delivery_date}</div>` : ''}
                        ${order.order_date ? `<div class="order-date"><span>Ordered: </span>${order.order_date}</div>` : ''}
                        <div class="pt-2 border-t order-status-wrapper border-gray-300/50">
                        ${event.extendedProps?.status ? `<div class="overflow-hidden order-status">${event.extendedProps.delivered_at ? 'Delivered ' . event.extendedProps.delivered_at : event.extendedProps.status}</div>` : ''}
                    </div>

                    </div>
                `).join('')}
            `;
            eventMainEl.replaceChildren(content);

            // Add click handlers after content is mounted
            el.querySelectorAll('.order-container').forEach(orderEl => {
                orderEl.addEventListener('click', (e) => {
                    e.stopPropagation();
                    Livewire.dispatch('calendar-order-clicked', { orderId: orderEl.dataset.orderId });
                });
            });

            // Add product toggle handlers
            el.querySelectorAll('.products-toggle').forEach(toggle => {
                toggle.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const productsList = toggle.nextElementSibling;
                    const chevron = toggle.querySelector('svg');
                    const isHidden = productsList.classList.contains('hidden');

                    productsList.classList.toggle('hidden');
                    chevron.style.transform = isHidden ? 'rotate(90deg)' : '';
                });
            });
        } else {
            // Standalone order content
            const content = document.createElement('div');
            content.innerHTML = `
                <div class="order-container status-${(event.extendedProps?.status || '').toLowerCase()}">
                    ${event.title ? `<div class="order-title">${event.title}</div>` : ''}
                    ${(event.extendedProps?.location_line1 || event.extendedProps?.location_line2) ? `
                        <div class="order-address">
                            ${event.extendedProps?.location_line2 ? `<div>${event.extendedProps.location_line2}</div>` : ''}
                        </div>
                    ` : ''}
                    <div class="pt-2 border-t order-status-wrapper border-gray-300/50">
                        ${(() => {
                            const status = event.extendedProps.status;
                            let statusText = status;

                            // Show different date info based on status
                            if (status === 'Delivered' && event.extendedProps.delivered_at) {
                                statusText = `${status} ${event.extendedProps.delivered_at}`;
                            }
                            else if (status === 'Out For Delivery' && event.extendedProps.start_time) {
                                statusText = `${status} at ${event.extendedProps.start_time}`;
                            }
                            else if (status === 'Pending') {
                                statusText = `${status} - Req: ${event.extendedProps.requested_delivery_date}`;
                            }
                            else if (status === 'Confirmed') {
                                statusText = `${status} - Ord: ${event.extendedProps.order_date}`;
                            }

                            return `<div class="overflow-hidden order-status">${statusText}</div>`;
                        })()}
                    </div>
                </div>
            `;
            eventMainEl.replaceChildren(content);

            // Add product toggle handlers for standalone orders
            el.querySelectorAll('.products-toggle').forEach(toggle => {
                toggle.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const productsList = toggle.nextElementSibling;
                    const chevron = toggle.querySelector('svg');
                    const isHidden = productsList.classList.contains('hidden');

                    productsList.classList.toggle('hidden');
                    chevron.style.transform = isHidden ? 'rotate(90deg)' : '';
                });
            });
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
                ->stickyModalFooter()
                ->createAnother(false)
                ->label('Create New Event')
                ->modalHeading(null)
                ->modalWidth('5xl')
                ->record(fn() => $this->record = null)
                ->form([
                    Select::make('type')
                        ->label('What would you like to create?')
                        ->options([
                            'order' => 'New Order',
                            'trip' => 'New Trip',
                        ])
                        ->default('order')
                        ->required()
                        ->live(),

                    // Order Form (shown when type = order)
                    ...collect(static::getOrderFormSchema($this->selectedDate))
                        ->map(fn($field) => $field->visible(fn(Get $get) => $get('type') === 'order'))
                        ->toArray(),

                    // Trip Form (shown when type = trip)
                    Section::make('Trip Details')
                        ->schema([
                            DatePicker::make('scheduled_date')
                                ->required()
                                ->default(fn() => $this->selectedDate)
                                ->native(false),
                            Select::make('driver_id')
                                ->relationship('driver', 'name')
                                ->options(function () {
                                    return Employee::whereHas('positions', function ($query) {
                                        $query->where('name', 'driver');
                                    })->pluck('name', 'id');
                                })
                                ->required(),
                            TextInput::make('notes')
                                ->maxLength(255),
                        ])
                        ->visible(fn(Get $get) => $get('type') === 'trip')
                        ->columns(2),

                    // Add Orders Section
                    Section::make('Orders')
                        ->schema([
                            Repeater::make('trip_orders')
                                ->schema([
                                    Select::make('order_id')
                                        ->label('Order')
                                        ->columnSpanFull()
                                        ->options(function (Get $get) {
                                            // Get all currently selected order IDs
                                            $selectedOrderIds = collect($get('../*.order_id'))
                                                ->filter()
                                                ->toArray();

                                            return Order::query()
                                                ->whereNull('trip_id')
                                                ->whereNotIn('id', $selectedOrderIds)  // Exclude already selected orders
                                                ->whereNotIn('status', ['delivered', 'cancelled'])
                                                ->with(['customer', 'location'])
                                                ->get()
                                                ->mapWithKeys(fn(Order $order) => [
                                                    $order->id => view('filament.components.order-option', [
                                                        'orderNumber' => $order->order_number,
                                                        'customerName' => $order->customer?->name,
                                                        'status' => $order->status,
                                                        'requestedDeliveryDate' => $order->requested_delivery_date?->format('M j'),
                                                        'assignedDeliveryDate' => $order->assigned_delivery_date?->format('M j'),
                                                        'location_line1' => $order->location?->address_line1,
                                                        'location_line2' => $order->location ?
                                                            "{$order->location->city}, {$order->location->state}"
                                                            : '',
                                                    ])->render()
                                                ]);
                                        })
                                        ->allowHtml()
                                        ->searchable()
                                        ->required(),
                                    TextInput::make('stop_number')
                                        ->numeric()
                                        ->default(
                                            function (Get $get) {
                                                $existingStops = array_filter($get('../*.stop_number'));
                                                return empty($existingStops) ? 1 : count($existingStops) + 1;
                                            }
                                        )
                                        ->required(),
                                    TextInput::make('delivery_notes')
                                        ->nullable(),
                                ])
                                ->columns(2)
                                ->reorderable()
                                ->reorderableWithButtons()
                                ->defaultItems(0)
                                ->addActionLabel('Add Order to Trip')
                        ])
                        ->visible(fn(Get $get) => $get('type') === 'trip'),
                ])
                ->action(function (array $data) {
                    if ($data['type'] === 'trip') {
                        // Create the trip first
                        $trip = Trip::create([
                            'scheduled_date' => $data['scheduled_date'],
                            'driver_id' => $data['driver_id'],
                            'notes' => $data['notes'] ?? null,
                            'status' => 'pending',
                        ]);

                        // Process each order in the repeater
                        if (isset($data['trip_orders'])) {
                            foreach ($data['trip_orders'] as $tripOrder) {
                                $order = Order::find($tripOrder['order_id']);

                                if (!$order) {
                                    continue;
                                }

                                $order->update([
                                    'trip_id' => $trip->id,
                                    'stop_number' => $tripOrder['stop_number'],
                                    'delivery_notes' => $tripOrder['delivery_notes'] ?? null,
                                    'assigned_delivery_date' => $data['scheduled_date']
                                ]);
                            }
                        }

                        $this->refreshCalendar();

                        Notification::make()
                            ->title('Trip created successfully')
                            ->success()
                            ->send();
                    } else {
                        // Get the mounted action data
                        $mountedData = json_decode(request()->input('components.0.snapshot'), true);
                        $formData = $mountedData['data']['mountedActionsData'][0][0][0] ?? [];

                        // Create the order
                        $order = Order::create(collect($data)->except(['type', 'orderProducts'])->toArray());

                        // Handle order products
                        if (isset($formData['orderProducts'][0])) {
                            $products = $formData['orderProducts'][0];

                            foreach ($products as $uuid => $product) {
                                if ($uuid === 's') continue;

                                // Get the product data from the first element of the product array
                                $productData = $product[0];

                                $order->orderProducts()->create([
                                    'product_id' => $productData['product_id'],
                                    'fill_load' => $productData['fill_load'] ?? false,
                                    'quantity' => $productData['quantity'] ?? null,
                                    'price' => $productData['price'] ?? 0,
                                    'location' => $productData['location'] ?? null,
                                    'notes' => $productData['notes'] ?? null,
                                ]);
                            }
                        }

                        $this->refreshCalendar();
                        return $order;
                    }
                })
                ->modalWidth('5xl'),
            Actions\EditAction::make()
                ->stickyModalFooter()
                ->modalWidth('5xl'),
            Actions\DeleteAction::make()
                ->action(function () {
                    if ($this->record instanceof Trip) {
                        $this->record->delete();
                    } else if ($this->record instanceof Order) {
                        $this->record->delete();
                    }

                    $this->refreshCalendar();
                    $this->dispatch('close-modal');
                })
                ->color(Color::Red)
                ->icon('heroicon-m-trash')
                ->requiresConfirmation(),
        ];
    }


    protected function viewAction(): Action
    {
        if ($this->record instanceof Trip) {
            return Actions\ViewAction::make('view')
                ->stickyModalFooter()
                ->modalFooterActions([
                    Actions\EditAction::make()
                        ->modalWidth('7xl')
                        ->stickyModalFooter(),
                    Actions\DeleteAction::make(),
                    Action::make('close')
                        ->label('Close')
                        ->color('gray')
                        ->action(fn() => $this->dispatch('close-modal')),
                ]);
        } else {
            return Actions\ViewAction::make('view')
                ->stickyModalFooter()
                ->modalContent(fn($record) => view(
                    'filament.resources.order-resource.custom-view',
                    ['record' => $record]
                ))
                ->modalHeading(fn($record) => $record->order_number)
                ->form([])
                ->modalFooterActions([
                    Actions\EditAction::make()
                        ->modalWidth('7xl')
                        ->stickyModalFooter(),
                    Actions\DeleteAction::make(),
                    Action::make('print')
                        ->label('Print Delivery Tag')
                        ->color('gray')
                        ->icon('heroicon-o-printer')
                        ->url(fn(Order $record) => route('orders.print', ['order' => $record]))
                        ->openUrlInNewTab(),
                ]);
        }
    }

    public function onDateSelect(string $start, string|null $end, bool $allDay, array|null $view, array|null $resource): void
    {
        $this->selectedDate = $start;
        $this->dispatch('date-selected');
    }

    public function handleDateSelected(): void
    {
        $this->mountAction('create');
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
        $tripEvents = Trip::with(['orders.customer', 'orders.orderProducts.product', 'orders.location', 'driver'])
            ->whereDate('scheduled_date', '>=', $fetchInfo['start'])
            ->whereDate('scheduled_date', '<=', $fetchInfo['end'])
            ->get()
            ->map(function (Trip $trip) {
                return [
                    'id' => 'trip_' . $trip->id,
                    'title' => "{$trip->trip_number}",
                    'start' => $trip->scheduled_date->format('Y-m-d'),
                    'allDay' => true,
                    'backgroundColor' => 'transparent',
                    // 'backgroundColor' => '#C6E7FF',
                    // 'textColor' => '#1f2937',
                    'classNames' => ['trip-event'],
                    'extendedProps' => [
                        'type' => 'trip',
                        'uuid' => $trip->uuid,
                        'status' => Str::headline($trip->status),
                        'driver_name' => $trip->driver?->name,
                        'orders' => $trip->orders->map(fn($order) => [
                            'id' => $order->id,
                            'title' => $order->customer?->name ?? $order->order_number,
                            'status' => Str::headline($order->status),
                            'requested_delivery_date' => $order->requested_delivery_date?->format('M j'),
                            'delivered_at' => $order->delivered_at?->format('M j, g:i A'),
                            'order_date' => $order->order_date?->format('M j'),
                            'extendedProps' => [
                                'location_line1' => $order->location?->address_line1,
                                'location_line2' => $order->location ?
                                    "{$order->location->city}, {$order->location->state}" : null,
                                'delivered_at' => $order->delivered_at?->format('M j, g:i A'),
                                'start_time' => $order->trip?->start_time?->format('g:i A'),
                                'arrived_at' => $order->arrived_at?->format('M j, g:i A'),
                            ],
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
            ->with(['customer', 'orderProducts.product', 'location'])
            ->whereDate('requested_delivery_date', '>=', $fetchInfo['start'])
            ->whereDate('requested_delivery_date', '<=', $fetchInfo['end'])
            ->get()
            ->map(function (Order $order) {
                return [
                    'id' => 'order_' . $order->id,
                    'title' => $order->customer?->name ?? $order->order_number,
                    'start' => $order->assigned_delivery_date?->format('Y-m-d') ?? $order->requested_delivery_date->format('Y-m-d'),
                    'allDay' => true,
                    'backgroundColor' => 'transparent',
                    'borderColor' => 'transparent',
                    'classNames' => [
                        'standalone-order',
                        'status-' . strtolower($order->status)
                    ],
                    'extendedProps' => [
                        'type' => 'order',
                        'uuid' => $order->uuid,
                        'status' => Str::headline($order->status),
                        'requested_delivery_date' => $order->requested_delivery_date?->format('M j'),
                        'order_date' => $order->order_date?->format('M j'),
                        'arrived_at' => $order->arrived_at?->format('M j, g:i A'),
                        'start_time' => $order->trip?->start_time?->format('g:i A'),
                        'delivered_at' => $order->delivered_at?->format('M j, g:i A'),
                        'delivery_notes' => $order->delivery_notes,
                        'location_line1' => $order->location?->address_line1,
                        'location_line2' => $order->location ?
                            "{$order->location->city}, {$order->location->state}" : null,
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
        // $startDate = $today->startOfMonth();
        // $endDate = $today->copy()->addMonths(2)->endOfMonth();

        return [
            // 'initialView' => 'dayGridMonth',
            'weekends' => false,
            'initialView' => 'dayGridMonth',
            // 'multiMonth' => [
            //     'months' => 3, // Show only 3 months at a time
            //     'startMonth' => $today->month,
            //     'startYear' => $today->year,
            // ],
            // 'validRange' => [
            //     'start' => $startDate->format('Y-m-d'),
            //     'end' => $endDate->format('Y-m-d'),
            // ],
            'dayMaxEvents' => false, // This will force all events to be shown
            'multiMonthMaxColumns' => 1,
            'selectable' => true,
            'editable' => true,
            'headerToolbar' => [
                'left' => 'prev,next today',
                'center' => 'title',
                'right' => 'dayGridMonth,timeGridWeek,timeGridDay,multiMonthYear',
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
                ->modalWidth('5xl')
                ->stickyModalFooter()
                ->form(fn() => $this->getFormSchema())
                ->action(function (array $data) {
                    $this->event->update($data);
                    $this->refreshRecords();
                }),
            Actions\DeleteAction::make(),
        ];
    }


    public function handleOrderClick($orderId)
    {

        $this->record = Order::with(['customer', 'orderProducts.product'])->find($orderId);
        $this->mountAction('view');
    }
}
