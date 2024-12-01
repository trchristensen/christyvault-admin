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

class TestCalendarComponent extends Component implements HasForms
{
    use HasOrderForm;
    use InteractsWithForms;

    public ?Order $editing = null;
    public ?Order $creating = null;
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function editOrder($orderId): void
    {
        $this->editing = Order::find($orderId);
        $this->data = $this->editing->toArray();
        $this->form->fill($this->data);
        $this->dispatch('open-modal', id: 'edit-order');
    }

    public function saveOrder(): void
    {
        $data = $this->form->getState();

        if ($this->editing) {
            $this->editing->update($data);
            $this->dispatch('close-modal', id: 'edit-order');
            $this->editing = null;
            $this->data = [];
            $this->dispatch('calendar-updated');
        }
    }

    public function createOrder($date): void
    {
        $this->creating = new Order();
        $this->data = [
            'requested_delivery_date' => $date,
            'assigned_delivery_date' => $date,
            // Set any other default values you need
        ];
        $this->form->fill($this->data);
        $this->dispatch('open-modal', id: 'create-order');
    }

    public function saveNewOrder(): void
    {
        $data = $this->form->getState();

        DB::beginTransaction();

        try {
            // Create the order
            $order = Order::create([
                'requested_delivery_date' => $data['requested_delivery_date'],
                'assigned_delivery_date' => $data['assigned_delivery_date'],
                'customer_id' => $data['customer_id'] ?? null,
                'location_id' => $data['location_id'] ?? null,
                'status' => $data['status'] ?? 'pending',
                'special_instructions' => $data['special_instructions'] ?? null,
                'order_date' => $data['order_date'] ?? now(),
                'delivery_time' => $data['delivery_time'] ?? null,
                'service_date' => $data['service_date'] ?? null,
            ]);

            // Handle order products
            if (!empty($data['orderProducts'])) {
                foreach ($data['orderProducts'] as $productData) {
                    $order->orderProducts()->create([
                        'product_id' => $productData['product_id'],
                        'quantity' => $productData['fill_load'] ? null : ($productData['quantity'] ?? null),
                        'fill_load' => $productData['fill_load'] ?? false,
                        'price' => $productData['price'] ?? 0,
                        'location' => $productData['location'] ?? null,
                        'notes' => $productData['notes'] ?? null,
                    ]);
                }
            }

            DB::commit();

            $this->dispatch('close-modal', id: 'create-order');
            $this->creating = null;
            $this->data = [];
            $this->dispatch('calendar-updated');
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema($this->getCreateOrderFormSchema())
            ->model($this->editing ?? $this->creating ?? new Order())
            ->statePath('data');
    }

    public function render()
    {
        // Get orders for the calendar
        $events = Order::query()
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
                        'isLocked' => $isLocked,
                        'products' => $order->orderProducts->map(function ($orderProduct) {
                            return [
                                'quantity' => $orderProduct->quantity,
                                'sku' => $orderProduct->product->sku,
                                'fill_load' => $orderProduct->fill_load
                            ];
                        })->toArray(),
                    ],
                ];
            })
            ->toArray();

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
