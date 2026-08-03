<?php

namespace App\Filament\Maintenance\Resources\MaintenanceRequestResource\Pages;

use App\Filament\Maintenance\Resources\MaintenanceRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMaintenanceRequests extends ListRecords
{
    protected static string $resource = MaintenanceRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
