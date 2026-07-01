<?php

namespace App\Filament\Resources\CalendarDayResource\Pages;

use App\Filament\Resources\CalendarDayResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCalendarDays extends ListRecords
{
    protected static string $resource = CalendarDayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
