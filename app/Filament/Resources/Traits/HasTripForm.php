<?php

namespace App\Filament\Resources\Traits;

use App\Models\Employee;
use App\Models\VehicleConfiguration;
use App\Services\TripOrderSelector;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

trait HasTripForm
{
    public static function getTripFormSchema(): array
    {
        return [
            Section::make('Trip Information')
                ->columnSpanFull()
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
                    Select::make('vehicle_configuration_id')
                        ->label('Vehicle Configuration')
                        ->relationship('vehicleConfiguration', 'name')
                        ->options(fn () => VehicleConfiguration::query()
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->helperText('Controls available rack spots and whether the piggyback forklift is onboard.'),
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
                        ->live()
                        ->native(false),
                    Textarea::make('notes')
                        ->columnSpanFull(),
                ])->columns(2),

            Section::make('Orders')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('orders')
                        ->schema([
                            Select::make('order_id')
                                ->label('Order')
                                ->columnSpanFull()
                                ->options(fn ($record, Get $get): array => app(TripOrderSelector::class)->options(
                                    $record,
                                    $get('../../scheduled_date'),
                                ))
                                ->allowHtml()
                                ->searchable()
                                ->searchDebounce(300)
                                ->searchPrompt('Search order #, location, address, city, or ZIP')
                                ->getSearchResultsUsing(fn (string $search, $record, Get $get): array => app(TripOrderSelector::class)->options(
                                    $record,
                                    $get('../../scheduled_date'),
                                    $search,
                                ))
                                ->getOptionLabelUsing(fn ($value): ?string => app(TripOrderSelector::class)->labelForValue($value))
                                ->required(),
                            TextInput::make('delivery_notes')
                                ->nullable()
                                ->columnSpanFull(),
                        ])
                        ->columns(2)
                        ->columnSpanFull()
                        ->reorderable()
                        ->reorderableWithButtons()
                        ->minItems(1)
                        ->required()
                        ->defaultItems(0)
                        ->helperText('The row order is the delivery stop order. Drag rows or use the arrows to reorder stops.')
                        ->addActionLabel('Add Order to Trip'),
                ]),
        ];
    }
}
