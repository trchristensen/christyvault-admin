<?php

namespace App\Filament\Resources\VehicleConfigurationResource\Pages;

use App\Filament\Resources\VehicleConfigurationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVehicleConfigurations extends ListRecords
{
    protected static string $resource = VehicleConfigurationResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
