<?php

namespace App\Filament\Resources\OrderResource\Widgets;

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


class CalendarWidget extends FullCalendarWidget
{
    public Model | string | null $model = Order::class;
    public ?Model $event = null;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';


    public function getViewData(): array
    {
        return [
            'pageTitle' => 'Delivery Calendar',
        ];
    }



    protected function getFormModel(): Model|string|null
    {
        return $this->event ?? Order::class;
    }

    // Resolve Event record into Model property
    public function resolveEventRecord(array $data): Order
    {
        return Order::find($data['id']);
    }
    public function getFormSchema(): array
    {
        return [
            Section::make('Order Details')
                ->description(fn(Order $order): mixed => $order->order_number)

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

                            // Get the customer's locations
                            $locations = Customer::find($state)?->locations()->get();

                            // If there's exactly one location, set it automatically
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
                        ->createOptionForm([
                            TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('address_line1')  // Changed from 'address'
                                ->required()
                                ->maxLength(255),
                            TextInput::make('address_line2')  // Added this optional field
                                ->maxLength(255),
                            TextInput::make('city')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('state')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('postal_code')    // Changed from 'zip'
                                ->required()
                                ->maxLength(20),
                            Select::make('location_type')     // Added required field
                                ->options([
                                    'business' => 'Business',
                                    'residential' => 'Residential',
                                    'funeral_home' => 'Funeral Home',
                                    'cemetery' => 'Cemetery',
                                    'other' => 'Other',
                                ])
                                ->required(),
                        ])
                        ->createOptionUsing(function (array $data, callable $get) {
                            $customerId = $get('customer_id');

                            return Customer::find($customerId)->locations()->create([
                                'name' => $data['name'],
                                'address_line1' => $data['address_line1'],
                                'address_line2' => $data['address_line2'],
                                'city' => $data['city'],
                                'state' => $data['state'],
                                'postal_code' => $data['postal_code'],
                                'location_type' => $data['location_type'],
                            ])->id;
                        })
                        ->visible(fn(callable $get) => (bool) $get('customer_id')),
                    DatePicker::make('order_date')
                        ->required()
                        ->default(now()->toDateString()),
                    DatePicker::make('requested_delivery_date')
                        ->required()
                        ->default(now()),
                    DatePicker::make('assigned_delivery_date')
                        // ->required()
                        ->time()
                        ->minDate(now()),
                    Select::make('status')
                        ->options([
                            'pending' => 'Pending',
                            'confirmed' => 'Confirmed',
                            'in_production' => 'In Production',
                            'ready_for_delivery' => 'Ready for Delivery',
                            'out_for_delivery' => 'Out for Delivery',
                            'delivered' => 'Delivered',
                            'cancelled' => 'Cancelled',
                            'in_progress' => 'In Progress',
                            'completed' => 'Completed',
                        ])
                        ->default('pending')
                        ->required(),
                    Textarea::make('special_instructions')
                        ->maxLength(1000),
                ])
                ->columns(2),
            Section::make('Products')
                ->schema([
                    Repeater::make('orderProducts')
                        ->relationship()
                        ->reorderable(true)
                        ->schema([
                            Select::make('product_id')
                                ->label('Product')
                                ->options(
                                    Product::query()
                                        ->whereRaw('is_active IS TRUE')
                                        ->get()
                                        ->mapWithKeys(fn(Product $product) => [
                                            $product->id => view('filament.components.product-option', [
                                                'sku' => $product->sku,
                                                'name' => $product->name,
                                            ])->render()
                                        ])
                                )
                                ->getOptionLabelsUsing(
                                    fn(array $values): array =>
                                    Product::whereIn(
                                        'id',
                                        $values
                                    )
                                        ->get()
                                        ->mapWithKeys(fn(Product $product) => [
                                            $product->id => view('filament.components.product-option', [
                                                'sku' => $product->sku,
                                                'name' => $product->name,
                                            ])->render()
                                        ])

                                        ->toArray()
                                )
                                ->allowHtml()
                                ->required()
                                ->reactive()
                                ->searchable()
                                ->afterStateUpdated(
                                    fn($state, callable $set) =>
                                    $set('price', Product::find($state)?->price ?? 0)
                                ),
                            Checkbox::make('fill_load')
                                ->label('Fill out load')
                                ->reactive()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    if ($state) {
                                        $set('quantity', null);
                                    }
                                }),
                            TextInput::make('quantity')
                                ->numeric()
                                ->default(1)
                                ->required(fn(Get $get): bool => !$get('fill_load'))
                                ->disabled(fn(Get $get): bool => $get('fill_load'))
                                ->dehydrated(fn(Get $get): bool => !$get('fill_load')),
                            TextInput::make('price')
                                ->numeric()
                                ->prefix('$')
                                ->required(),
                            TextInput::make('location')
                                ->nullable(),
                            TextInput::make('notes')
                                ->nullable()
                                ->columnSpanFull()
                        ])
                        ->columns(3)
                ]),
        ];
    }


    public function onEventDrop(array $event, array $oldEvent, array $relatedEvents, array $delta, ?array $oldResource, ?array $newResource): bool
    {
        $order = Order::find($event['id']);
        if (!$order) {
            Log::warning('Order not found', context: ['event_id' => $event['id']]);
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
    }

    public function eventDidMount(): string
    {
        return <<<'JS'
    function({ event, el }) {
        const eventMainEl = el.querySelector('.fc-event-main');
        eventMainEl.style.padding = '12px';

        // Add locked indicator for in_progress/completed orders
        if (['in_progress', 'completed', 'delivered'].includes(event.extendedProps.status.toLowerCase())) {
            el.style.cursor = 'not-allowed';
            el.style.opacity = '0.8';

            // Add a lock icon
            const lockIcon = document.createElement('div');
            lockIcon.innerHTML = '🔒';
            lockIcon.style.position = 'absolute';
            lockIcon.style.top = '4px';
            lockIcon.style.right = '4px';
            lockIcon.style.fontSize = '12px';
            el.appendChild(lockIcon);
        }

        // Main heading - Customer Name/Order Number
        const titleEl = document.createElement('div');
        titleEl.style.fontSize = '14px';
        titleEl.style.fontWeight = '500';
        titleEl.style.marginBottom = '8px';
        titleEl.textContent = event.title;

        // Info container
        const infoContainer = document.createElement('div');
        infoContainer.style.fontSize = '12px';
        infoContainer.style.color = 'rgba(255, 255, 255, 0.8)';
        infoContainer.style.display = 'flex';
        infoContainer.style.flexDirection = 'column';
        infoContainer.style.gap = '4px';

        // Meta info
        infoContainer.innerHTML = `
            <div><span>requested: </span>${event.extendedProps.requestedDate}</div>
            <div><span>status: </span>${event.extendedProps.status}</div>
        `;

        // Products list
        if (event.extendedProps.products.length > 0) {
            const productsEl = document.createElement('div');
            productsEl.style.marginTop = '8px';
            productsEl.style.borderTop = '1px solid rgba(255, 255, 255, 0.1)';
            productsEl.style.paddingTop = '8px';

            productsEl.innerHTML = event.extendedProps.products
                .map(p => `
                    <div style="display: flex; align-items: center; gap: 6px; margin-top: 4px;">
                        <span style="opacity: 0.6">${p.fill_load ? '*' : p.quantity} </span>
                        <span>${p.sku}</span> ${p.fill_load ? '<span style="opacity: 0.6">(fill load)</span>' : '' }
                    </div>
                `).join('');

            infoContainer.appendChild(productsEl);
        }

        // Clear and add new elements
        eventMainEl.innerHTML = '';
        eventMainEl.appendChild(titleEl);
        eventMainEl.appendChild(infoContainer);

        // Add hover effect
        el.style.transition = 'transform 0.2s ease';
        el.addEventListener('mouseenter', () => {
            el.style.transform = 'scale(1.02)';
        });
        el.addEventListener('mouseleave', () => {
            el.style.transform = 'scale(1)';
        });
    }
    JS;
    }


    protected function modalActions(): array
    {
        $order = $this->event;

        // If order is in progress or completed, return empty array (no actions)
        if ($order && in_array($order->status, ['in_progress', 'completed', 'delivered'])) {
            return [];
        }

        return [
            Actions\EditAction::make()
                ->modalSubmitActionLabel('Save changes'),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getActions(): array
    {
        $order = $this->event;

        // If order is in progress or completed, return empty array (no actions)
        if ($order && in_array($order->status, ['in_progress', 'completed', 'delivered'])) {
            return [];
        }

        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function viewAction(): \Filament\Actions\Action
    {
        return Actions\EditAction::make();
    }

    protected function getEventColor(Order $order): string
    {
        return match ($order->status) {
            'pending' => '#64748B',      // Slate
            'confirmed' => '#3B82F6',    // Blue
            'in_production' => '#3B82F6',
            'ready_for_delivery' => '#3B82F6',
            'out_for_delivery' => '#3B82F6',
            'delivered' => '#1E3A8A',    // Dark Blue
            'cancelled' => '#7F1D1D',    // Dark Red
            default => '#64748B',
        };
    }



    public function refreshRecords(): void
    {
        $this->dispatch('filament-fullcalendar--refresh');
    }

    public function fetchEvents(array $fetchInfo): array
    {
        return Order::query()
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
    }
}
