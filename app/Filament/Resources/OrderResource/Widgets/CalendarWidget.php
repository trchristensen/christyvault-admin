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
use App\Models\Trip;


class CalendarWidget extends FullCalendarWidget
{
    use HasOrderForm;

    public Model | string | null $model = Order::class;
    public ?Model $event = null;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected bool $selectable = true;
    protected bool $editable = true;

    public ?string $selectedDate = null;

    public function getViewData(): array
    {
        return [
            'pageTitle' => 'Delivery Calendar',
        ];
    }



    protected function getFormModel(): Model|string|null
    {
        if ($this->event instanceof Trip) {
            return $this->event ?? Trip::class;
        }
        return $this->event ?? Order::class;
    }

    // Resolve Event record into Model property
    public function resolveEventRecord(array $data): ?Model
    {
        $id = $data['id'];

        if (str_starts_with($id, 'trip_')) {
            $tripId = (int)substr($id, 5);
            return Trip::find($tripId);
        } else if (str_starts_with($id, 'order_')) {
            $orderId = (int)substr($id, 6);
            return Order::find($orderId);
        }

        return null;
    }
    public function getFormSchema(): array
    {
        if ($this->event instanceof Trip) {
            return [
                // Trip form schema here
            ];
        }
        return static::getOrderFormSchema();
    }


    public function onEventDrop(array $event, array $oldEvent, array $relatedEvents, array $delta, ?array $oldResource, ?array $newResource): bool
    {
        // Extract the numeric ID from the prefixed string
        $id = $event['id'];
        if (str_starts_with($id, 'order_')) {
            $orderId = substr($id, 6); // Remove 'order_' prefix
            $order = Order::find($orderId);

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
            $tripId = substr($id, 5); // Remove 'trip_' prefix
            $trip = Trip::find($tripId);

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
        eventMainEl.style.padding = '12px';

        if (event.extendedProps.type === 'trip') {
            // Trip styling
            el.style.borderLeft = '4px solid #1E40AF';

            // Trip content
            const content = document.createElement('div');
            content.innerHTML = `
                <div style="font-weight: 500; margin-bottom: 8px;">${event.title}</div>
                ${event.extendedProps.orders.map(order => `
                    <div style="background: rgba(255,255,255,0.1); padding: 8px; margin-top: 8px; border-radius: 4px;">
                        <div style="font-weight: 500;">${order.title}</div>
                        <div style="font-size: 0.9em;">Status: ${order.status}</div>
                        ${order.products.map(p => `
                            <div style="font-size: 0.8em;">
                                ${p.fill_load ? '*' : p.quantity} × ${p.sku} ${p.fill_load ? '(fill load)' : ''}
                            </div>
                        `).join('')}
                    </div>
                `).join('')}
            `;
            eventMainEl.replaceChildren(content);
        } else {
            // Original Order styling and content
            // ... your existing order event styling code ...
        }
    }
    JS;
    }


    protected function modalActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Create New')
                ->modalHeading('Create New')
                ->modalWidth('2xl')
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
                    DatePicker::make('scheduled_date')
                        ->required()
                        ->native(false)
                        ->visible(fn(Get $get) => $get('type') === 'trip'),
                    Select::make('driver_id')
                        ->relationship('driver', 'name')
                        ->required()
                        ->visible(fn(Get $get) => $get('type') === 'trip'),
                    TextInput::make('notes')
                        ->maxLength(255)
                        ->visible(fn(Get $get) => $get('type') === 'trip'),
                ])
                ->action(function (array $data) {
                    if ($data['type'] === 'order') {
                        Order::create([
                            ...collect($data)->except('type')->toArray(),
                            'order_date' => now(),
                            'status' => OrderStatus::PENDING->value,
                        ]);
                    } else {
                        // Generate trip number (assuming format TRIP-XXXXX)
                        $lastTrip = Trip::orderBy('id', 'desc')->first();
                        $nextNumber = $lastTrip ? ((int)substr($lastTrip->trip_number, 5) + 1) : 1;
                        $tripNumber = 'TRIP-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

                        Trip::create([
                            ...collect($data)->except('type')->toArray(),
                            'trip_number' => $tripNumber,
                        ]);
                    }
                    $this->refreshRecords();
                }),
        ];
    }



    // protected function getActions(): array
    // {
    //     $event = $this->event;


    //     // If it's a Trip
    //     if ($event instanceof Trip) {
    //         return [
    //             Actions\EditAction::make(),
    //             Actions\DeleteAction::make(),
    //         ];
    //     }

    //     // If it's an Order and is in progress or completed, return empty array (no actions)
    //     if ($event instanceof Order && in_array($event->status, [OrderStatus::OUT_FOR_DELIVERY, OrderStatus::COMPLETED, OrderStatus::DELIVERED])) {
    //         return [];
    //     }

    //     // Default actions for Orders
    //     return [
    //         Actions\EditAction::make(),
    //         Actions\DeleteAction::make(),
    //     ];
    // }





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
                    'id' => $trip->id, // Prefix to distinguish from orders
                    'title' => "{$trip->trip_number}\n{$trip->driver?->name}",
                    'start' => $trip->scheduled_date->format('Y-m-d'),
                    'allDay' => true,
                    'backgroundColor' => '#2563EB',
                    'borderColor' => '#1E40AF',
                    'classNames' => ['trip-event'],
                    'extendedProps' => [
                        'type' => 'trip',
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
                    'id' => $order->id,
                    'title' => $order->customer?->name ?? $order->order_number,
                    'start' => $order->assigned_delivery_date?->format('Y-m-d') ?? $order->requested_delivery_date->format('Y-m-d'),
                    'allDay' => true,
                    'backgroundColor' => $order->assigned_delivery_date ? $this->getEventColor($order) : 'grey',
                    'borderColor' => 'transparent',
                    'editable' => !$isLocked,
                    'extendedProps' => [
                        'type' => 'order',
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
        return [
            'initialView' => 'dayGridMonth',
            'selectable' => true,
            'editable' => true,
            'headerToolbar' => [
                'left' => 'prev,next today',
                'center' => 'title',
                'right' => 'dayGridMonth,timeGridWeek,timeGridDay',
            ],
        ];
    }
}
