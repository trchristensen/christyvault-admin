<?php

namespace App\Filament\Resources\CalendarDayResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\CalendarDayResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCalendarDay extends EditRecord
{
    protected static string $resource = CalendarDayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
