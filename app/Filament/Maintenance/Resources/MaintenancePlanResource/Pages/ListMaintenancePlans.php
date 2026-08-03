<?php

namespace App\Filament\Maintenance\Resources\MaintenancePlanResource\Pages;

use App\Filament\Maintenance\Resources\MaintenancePlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMaintenancePlans extends ListRecords
{
    protected static string $resource = MaintenancePlanResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
