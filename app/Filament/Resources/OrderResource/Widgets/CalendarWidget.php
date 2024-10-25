<?php

namespace App\Filament\Resources\OrderResource\Widgets;

use App\Filament\Resources\OrderResource;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Barryvdh\Snappy\Facades\SnappyPdf;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Support\Components\ViewComponent;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;
use Saade\FilamentFullCalendar\Actions;
use Illuminate\Support\Str;

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
                ->schema([
                    Select::make('customer_id')
                        ->label('Customer')
                        ->options(Customer::pluck('name', 'id'))
                        ->required()
                        ->searchable(),
                    DatePicker::make('requested_delivery_date')
                        ->required()
                        ->minDate(now()),
                    DatePicker::make('actual_delivery_date')
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
                        ->maxLength(1000),
                ])
                ->columns(2),
            Section::make('Products')
                ->schema([
                    Repeater::make('orderProducts')
                        ->relationship()
                        ->schema([
                            Select::make('product_id')
                                ->label('Product')
                                ->options(Product::query()->pluck('name', 'id'))
                                ->required()
                                ->reactive()
                                ->afterStateUpdated(
                                    fn($state, callable $set) =>
                                    $set('price', Product::find($state)?->price ?? 0)
                                ),
                            TextInput::make('quantity')
                                ->numeric()
                                ->default(1)
                                ->required(),
                            TextInput::make('price')
                                ->numeric()
                                ->prefix('$')
                                ->required(),
                        ])
                        ->columns(3),
                ])
        ];
    }


    protected function modalActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }




    public function onEventDrop(array $event, array $oldEvent, array $relatedEvents, array $delta, ?array $oldResource, ?array $newResource): bool
    {
        $order = Order::find($event['id']);
        if ($order) {
            try {
                $newDate = Carbon::parse($event['start'])->toDateString();
                $order->update([
                    'actual_delivery_date' => $newDate,
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
            
            const requestedDateEl = document.createElement('div');
            requestedDateEl.className = 'fc-event-line';
            requestedDateEl.textContent = '🙏🏽 ' + event.extendedProps.requestedDate;

            const driverNameEl = document.createElement('div');
            driverNameEl.className = 'fc-event-line';
            driverNameEl.textContent = '🚚 ' + event.extendedProps.driverName;

            const statusEl = document.createElement('div');
            statusEl.className = 'fc-event-line';
            statusEl.textContent = '📣 ' + event.extendedProps.status;

            eventMainEl.appendChild(requestedDateEl);
            eventMainEl.appendChild(driverNameEl);
            eventMainEl.appendChild(statusEl);

            eventMainEl.style.display = 'flex';
            eventMainEl.style.flexDirection = 'column';
        }
        JS;
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
                return [
                    'id' => $order->id,
                    'title' => $order->customer?->name ?? $order->order_number,
                    'start' => $order->actual_delivery_date?->format('Y-m-d') ?? $order->requested_delivery_date->format('Y-m-d'),
                    'allDay' => true,
                    'backgroundColor' => $order->actual_delivery_date ? $this->getEventColor($order) : 'grey',
                    'borderColor' => 'transparent',
                    'extendedProps' => [
                        'customerName' => $order->customer?->name,
                        'requestedDate' => $order->requested_delivery_date->format('m/d'),
                        'driverName' => $order->driver?->name ? Str::headline($order->driver?->name) : 'Unassigned',
                        'status' => Str::headline($order->status),
                    ],
                ];
            })
            ->toArray();
    }

    protected function getEventColor(Order $order): string
    {
        return match ($order->status) {
            'pending' => '#808080',
            'confirmed' => '#1E90FF',
            'in_production' => '#1E90FF',
            'ready_for_delivery' => '#1E90FF',
            'out_for_delivery' => '#1E90FF',
            'delivered' => '#1c3366',
            'cancelled' => '#B80C09',
            default => '#808080',
        };
    }
}
