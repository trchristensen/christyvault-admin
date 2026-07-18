<?php

namespace App\Filament\Resources\VehicleConfigurationResource\Pages;

use App\Filament\Resources\VehicleConfigurationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVehicleConfiguration extends EditRecord
{
    protected static string $resource = VehicleConfigurationResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
