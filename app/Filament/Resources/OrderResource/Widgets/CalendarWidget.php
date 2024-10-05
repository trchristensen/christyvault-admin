<?php

namespace App\Filament\Resources\OrderResource\Widgets;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;
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
            DatePicker::make('actual_delivery_date')
                ->label('Actual Delivery Date')
                ->required(),
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

    // public function onEventDrop(array $event): void
    // {
    //     $order = Order::find($event['id']);
    //     if ($order) {
    //         $order->update([
    //             'actual_delivery_date' => Carbon::parse($event['start'])->toDateString(),
    //         ]);
    //     }
    // }

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
                    'backgroundColor' => $order->actual_delivery_date ? 'green' : 'blue',
                    'extendedProps' => [
                        'requestedDate' => $order->requested_delivery_date->format('Y-m-d'),
                    ],
                    'description' => "Requested: " . $order->requested_delivery_date->format('Y-m-d'),
                    'url' => OrderResource::getUrl('edit', ['record' => $order]),
                    'shouldOpenInNewTab' => true,
                ];
            })
            ->toArray();
    }
}
