<?php

namespace App\Filament\Resources\OrderResource\Widgets;

use App\Filament\Resources\OrderResource;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;
use Saade\FilamentFullCalendar\Actions;

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


    protected function headerActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
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
                        // ->required()
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
                        ->columns(3)
                ])
        ];
    }

    protected function modalActions(): array
    {
        return [
            Actions\EditAction::make()
                ->mountUsing(
                    function (Order $record, Form $form, array $arguments) {
                        $form->fill([
                            'actual_delivery_date' => $arguments['event']['start'] ?? $record->actual_delivery_date,
                        ]);
                    }
                )
                ->action(function (Order $record, array $data): void {
                    $record->update([
                        'actual_delivery_date' => $data['actual_delivery_date'],
                    ]);
                }),
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
                // Refresh the calendar
                // $this->refreshCalendar();
                $this->refreshRecords();


                return true;
            } catch (\Exception $e) {
                return false;
            }
        }
        Log::warning('Order not found', context: ['event_id' => $event['id']]);
        return false;
    }



    // Tooltip
    public function eventDidMount(): string
    {
        return <<<JS
        function({ event, el }) {
            // Create the description element
            const descriptionEl = document.createElement('div');
            descriptionEl.className = 'fc-event-description';
            descriptionEl.textContent = event.extendedProps.description || '';

            // Append the description after the existing title
            const eventMainEl = el.querySelector('.fc-event-main');
            eventMainEl.appendChild(descriptionEl);

            // Ensure the content is properly styled
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
            ->whereDate('requested_delivery_date', '>=', $fetchInfo['start'])
            ->whereDate('requested_delivery_date', '<=', $fetchInfo['end'])
            ->get()
            ->map(function (Order $order) {
                return [
                    'id' => $order->id,
                    'title' => $order->customer?->name ?? $order->order_number,
                    'start' => $order->actual_delivery_date?->format('Y-m-d') ?? $order->requested_delivery_date->format('Y-m-d'),
                    'allDay' => true,
                    'backgroundColor' => $order->actual_delivery_date ? 'light-blue' : 'grey',
                    'extendedProps' => [
                        'requestedDate' => $order->requested_delivery_date->format('Y-m-d'),
                        'description' => "Requested: " . $order->requested_delivery_date->format('Y-m-d'),
                    ],
                    'description' => "Requested: " . $order->requested_delivery_date->format('Y-m-d'),
                    'url' => OrderResource::getUrl('edit', ['record' => $order]),
                    'shouldOpenInNewTab' => true,
                ];
            })
            ->toArray();
    }
}
