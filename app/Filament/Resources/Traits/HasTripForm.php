<?php

namespace App\Filament\Resources\Traits;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Section;
use App\Models\Employee;

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
                ])->columns(2)
        ];
    }
}
