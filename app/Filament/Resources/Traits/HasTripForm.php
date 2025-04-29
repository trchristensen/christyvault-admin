<?php

namespace App\Filament\Resources\Traits;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Section;
use App\Models\Employee;
use App\Models\Order;

trait HasTripForm
{
    public static function getTripFormSchema(): array
    {
        return [
            Section::make('Trip Information')
                ->schema([
                    TextInput::make('trip_number')
                        ->disabled()
                        ->dehydrated(false),
                     Select::make('driver_id')
                        ->relationship('driver', 'name')
                        ->options(function () {
                            return Employee::whereHas('positions', function ($query) {
                                $query->where('name', 'driver');
                            })->pluck('name', 'id');
                        })
                        ->required(),
                    Select::make('status')
                        ->options([
                            'pending' => 'Pending',
                            'confirmed' => 'Confirmed',
                            'in_progress' => 'In Progress',
                            'completed' => 'Completed',
                            'cancelled' => 'Cancelled',
                        ])
                        ->required(),
                    DatePicker::make('scheduled_date')
                        ->required()
                        ->native(false),
                    Textarea::make('notes')
                        ->columnSpanFull(),
                ])->columns(2),

            Section::make('Orders')
                ->schema([
                    \Filament\Forms\Components\Repeater::make('orders')
                        ->relationship()
                        ->schema([
                            Select::make('id')
                                ->label('Order')
                                ->columnSpanFull()
                                ->options(function ($record) {
                                    $query = Order::query()
                                        ->where(function ($query) use ($record) {
                                            $query->whereNull('trip_id')
                                                ->orWhere('id', $record?->id);
                                        })
                                        ->whereNotIn('status', ['delivered', 'cancelled'])
                                        ->with(['location']);

                                    return $query->get()
                                        ->mapWithKeys(fn(Order $order) => [
                                            $order->id => view('filament.components.order-option', [
                                                'orderNumber' => $order->order_number,
                                                'customerName' => $order->location?->name,
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
                                    function () {
                                        return 1;
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
        ];
    }
}
