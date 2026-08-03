<?php

namespace App\Filament\Maintenance\Resources\MaintenanceMeterReadingResource\Pages;

use App\Filament\Maintenance\Resources\MaintenanceMeterReadingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMaintenanceMeterReading extends CreateRecord
{
    protected static string $resource = MaintenanceMeterReadingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['recorded_by_user_id'] = auth()->id();

        return $data;
    }
}
