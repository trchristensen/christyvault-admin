<?php

namespace App\Livewire;

use App\Models\Order;
use App\Enums\OrderStatus;
use Illuminate\Support\Str;
use Livewire\Component;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Support\Contracts\TranslatableContentDriver;
use App\Filament\Resources\Traits\HasOrderForm;
use Filament\Forms\Form;
use Illuminate\Support\Facades\DB;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Hidden;
use App\Models\Customer;
use App\Models\Product;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use App\Models\Trip;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Illuminate\Database\Eloquent\Model;
use Filament\Actions\Actions;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;

class TestCalendarComponent extends Component implements HasForms, HasActions
{
    use HasOrderForm;
    use InteractsWithForms;
    use InteractsWithActions;

    public ?Order $event = null;
    public ?array $data = [];

    protected function getFormModel(): Model|string|null
    {
        return $this->event ?? Order::class;
    }

    protected function getActions(): array
    {
        return [
            CreateAction::make()
                ->model(Order::class)
                ->form($this->getOrderFormSchema())
                ->mutateFormDataUsing(function (array $data): array {
                    return [
                        ...$data,
                        'order_date' => now(),
                        'status' => OrderStatus::PENDING->value,
                    ];
                }),
            EditAction::make()
                ->model(Order::class)
                ->form($this->getOrderFormSchema()),
        ];
    }

    public function createOrder($date): void
    {
        $this->data = [
            'requested_delivery_date' => $date,
            'assigned_delivery_date' => $date,
            'orderProducts' => [
                ['product_id' => null, 'quantity' => 1, 'fill_load' => false, 'price' => 0]
            ]
        ];

        $this->mountAction('create', ['data' => $this->data]);
    }

    public function editOrder($orderId): void
    {
        $this->event = Order::find($orderId);
        if ($this->event) {
            $this->data = $this->event->toArray();
            $this->mountAction('edit', ['record' => $this->event]);
        }
    }

    public function render()
    {
        // Get trips with their orders
        $tripEvents = Trip::with(['orders.customer', 'orders.orderProducts.product', 'driver'])
            ->get()
            ->map(function (Trip $trip) {
                return [
                    'id' => $trip->id,
                    'title' => "<div style='line-height: 1.2'>" .
                        "{$trip->trip_number}" .
                        "<div style='font-size: 0.9em; opacity: 0.9'>{$trip->driver?->name}</div>" .
                        "</div>",
                    'start' => $trip->scheduled_date->format('Y-m-d'),
                    'allDay' => true,
                    'backgroundColor' => '#2563EB', // Blue background for trips
                    'borderColor' => '#1E40AF',
                    'textColor' => '#ffffff',
                    'classNames' => ['trip-event'],
                    'extendedProps' => [
                        'type' => 'trip',
                        'orders' => $trip->orders->map(function ($order) {
                            return [
                                'id' => $order->id,
                                'title' => $order->customer?->name ?? $order->order_number,
                                'status' => Str::headline($order->status),
                                'products' => $order->orderProducts->map(function ($orderProduct) {
                                    return [
                                        'quantity' => $orderProduct->quantity,
                                        'sku' => $orderProduct->product->sku,
                                        'fill_load' => $orderProduct->fill_load
                                    ];
                                })->toArray(),
                            ];
                        })->toArray()
                    ],
                ];
            });

        // Get standalone orders (not assigned to trips)
        $orderEvents = Order::whereNull('trip_id')
            ->with(['customer', 'orderProducts.product'])
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
                        'customerName' => $order->customer?->name,
                        'requestedDate' => $order->requested_delivery_date->format('m/d'),
                        'status' => Str::headline($order->status),
                        // 'isLocked' => $isLocked,
                        'isLocked' => false,
                        'products' => $order->orderProducts->map(function ($orderProduct) {
                            return [
                                'quantity' => $orderProduct->quantity,
                                'sku' => $orderProduct->product->sku,
                                'fill_load' => $orderProduct->fill_load
                            ];
                        })->toArray(),
                    ],
                ];
            });

        $events = $tripEvents->concat($orderEvents);

        return view('livewire.test-calendar-component', [
            'events' => $events,
        ]);
    }

    // Required by HasForms interface
    public function getFormStatePath(): ?string
    {
        return 'data';
    }

    public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
    {
        return null;
    }

    protected function getEventColor(Order $order): string
    {
        $status = OrderStatus::from($order->status);
        return $status->color();
    }

    public function updateOrderDate($orderId, $newDate): bool
    {
        $order = Order::find($orderId);
        if ($order) {
            try {
                $order->update([
                    'assigned_delivery_date' => $newDate
                ]);

                // Return true to indicate success
                return true;
            } catch (\Exception $e) {
                // Return false to indicate failure
                return false;
            }
        }
        return false;
    }

    protected function getCreateOrderFormSchema(): array
    {
        return [
            Section::make('Order Details')
                ->schema([
                    Select::make('customer_id')
                        ->label('Customer')
                        ->options(Customer::pluck('name', 'id'))
                        ->required()
                        ->searchable()
                        ->reactive()
                        ->createOptionForm([
                            TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('email')
                                ->email()
                                ->required()
                                ->maxLength(255),
                            TextInput::make('phone')
                                ->tel()
                                ->required()
                                ->maxLength(255),
                        ])
                        ->createOptionUsing(function (array $data) {
                            return Customer::create([
                                'name' => $data['name'],
                                'email' => $data['email'],
                                'phone' => $data['phone'],
                            ])->id;
                        })
                        ->afterStateUpdated(function (callable $set, $state) {
                            if (!$state) {
                                $set('location_id', null);
                                return;
                            }
                            $locations = Customer::find($state)?->locations()->get();
                            if ($locations && $locations->count() === 1) {
                                $set('location_id', $locations->first()->id);
                            } else {
                                $set('location_id', null);
                            }
                        }),

                    Select::make('location_id')
                        ->label('Delivery Location')
                        ->options(function (callable $get) {
                            $customerId = $get('customer_id');
                            if (!$customerId) return [];
                            return Customer::find($customerId)
                                ?->locations()
                                ->get()
                                ->mapWithKeys(fn($location) => [
                                    $location->id => $location->full_address
                                ]) ?? [];
                        })
                        ->required()
                        ->searchable()
                        ->disabled(fn(callable $get) => empty($get('customer_id'))),

                    DatePicker::make('order_date')
                        ->required()
                        ->native(false)
                        ->default(now()->toDateString()),

                    DatePicker::make('requested_delivery_date')
                        ->required()
                        ->native(false)
                        ->default(now()),

                    DatePicker::make('assigned_delivery_date')
                        ->native(false)
                        ->minDate(today()),

                    Select::make('status')
                        ->options(collect(OrderStatus::cases())->mapWithKeys(function ($status) {
                            return [$status->value => str($status->value)
                                ->replace('_', ' ')
                                ->title()
                                ->toString()];
                        }))
                        ->default(OrderStatus::PENDING->value)
                        ->required(),

                    TimePicker::make('delivery_time')
                        ->label("Deliver By")
                        ->nullable()
                        ->seconds(false),

                    DateTimePicker::make('service_date')
                        ->nullable()
                        ->native(false)
                        ->seconds(false),

                    Textarea::make('special_instructions')
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('Products')
                ->schema([
                    Repeater::make('orderProducts')
                        ->schema([
                            Select::make('product_id')
                                ->columnSpanFull()
                                ->label('Product')
                                ->options(
                                    Product::query()
                                        ->active()
                                        ->get()
                                        ->mapWithKeys(fn(Product $product) => [
                                            $product->id => view('filament.components.product-option', [
                                                'sku' => $product->sku,
                                                'name' => $product->name,
                                            ])->render()
                                        ])
                                )
                                ->allowHtml()
                                ->required()
                                ->reactive()
                                ->searchable()
                                ->afterStateUpdated(
                                    fn($state, callable $set) =>
                                    $set('price', Product::find($state)?->price ?? 0)
                                ),
                            Toggle::make('fill_load')
                                ->label('Fill out load')
                                ->inline(false)
                                ->reactive()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    if ($state) {
                                        $set('quantity', null);
                                    }
                                }),
                            TextInput::make('quantity')
                                ->numeric()
                                ->default(1)
                                ->required(fn(callable $get): bool => !$get('fill_load'))
                                ->disabled(fn(callable $get): bool => $get('fill_load'))
                                ->dehydrated(fn(callable $get): bool => !$get('fill_load')),
                            Hidden::make('price')
                                ->default(0),
                            TextInput::make('location')
                                ->nullable(),
                            TextInput::make('notes')
                                ->nullable()
                                ->columnSpanFull()
                        ])
                        ->defaultItems(1)
                        ->columns(3)
                ]),
        ];
    }
}
