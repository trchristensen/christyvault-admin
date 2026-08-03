<?php

namespace App\Filament\Maintenance\Resources\MaintenanceMeterReadingResource\Pages;

use App\Filament\Maintenance\Resources\MaintenanceMeterReadingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMaintenanceMeterReadings extends ListRecords
{
    protected static string $resource = MaintenanceMeterReadingResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
