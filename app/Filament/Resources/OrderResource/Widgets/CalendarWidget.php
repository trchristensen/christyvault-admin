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

class CalendarWidget extends FullCalendarWidget
{
    public Model | string | null $model = Order::class;

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
                ->description(fn(Order $order) => $order->order_number)
                ->schema([
                    Select::make('customer_id')
                        ->label('Customer')
                        ->options(Customer::pluck('name', 'id'))
                        ->required()
                        ->searchable(),
                    DatePicker::make('requested_delivery_date')
                        ->required()
                        ->default(now())
                        ->minDate(now()),
                    DatePicker::make('assigned_delivery_date')
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
                        ])
                        ->default('pending')
                        ->required(),
                    Textarea::make('special_instructions')
                        ->maxLength(1000)
                        ->columnSpanFull(),
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
                                ->columnSpanFull()
                                ->options(
                                    Product::query()
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
                                    Product::whereIn('id', $values)
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
                                ->searchable()
                                ->required()
                                ->reactive()
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
                                ->required(fn($get): bool => !$get('fill_load'))
                                ->disabled(fn($get): bool => $get('fill_load'))
                                ->dehydrated(fn($get): bool => !$get('fill_load')),
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
            Select::make('driver_id')
                ->label('Driver')
                ->options(Employee::where('position', 'driver')->pluck('name', 'id'))
                ->searchable()
                ->nullable(),
        ];
    }


    public function onEventDrop(array $event, array $oldEvent, array $relatedEvents, array $delta, ?array $oldResource, ?array $newResource): bool
    {

        $order = Order::find($event['id']);
        if ($order) {

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
        Log::warning('Order not found', context: ['event_id' => $event['id']]);
        return false;
    }

    public function eventDidMount(): string
    {
        return <<<'JS'
    function({ event, el }) {
        const eventMainEl = el.querySelector('.fc-event-main');
        eventMainEl.style.padding = '12px';

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
            <div><span>driver: </span>${event.extendedProps.driverName || 'Unassigned'}</div>
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
        return [
            Actions\EditAction::make()
                ->modalSubmitActionLabel('Save changes'),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getActions(): array
    {
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
            ->with(['customer', 'orderProducts.product'])  // Make sure we're eager loading products
            ->whereDate('requested_delivery_date', '>=', $fetchInfo['start'])
            ->whereDate('requested_delivery_date', '<=', $fetchInfo['end'])
            ->get()
            ->map(function (Order $order) {
                return [
                    'id' => $order->id,
                    'title' => $order->customer?->name ?? $order->order_number,
                    'start' => $order->assigned_delivery_date?->format('Y-m-d') ?? $order->requested_delivery_date->format('Y-m-d'),
                    'allDay' => true,
                    'backgroundColor' => $order->assigned_delivery_date ? $this->getEventColor($order) : 'grey',
                    'borderColor' => 'transparent',
                    'extendedProps' => [
                        'customerName' => $order->customer?->name,
                        'requestedDate' => $order->requested_delivery_date->format('m/d'),
                        'driverName' => $order->driver?->name ? Str::headline($order->driver?->name) : 'Unassigned',
                        'status' => Str::headline($order->status),
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
