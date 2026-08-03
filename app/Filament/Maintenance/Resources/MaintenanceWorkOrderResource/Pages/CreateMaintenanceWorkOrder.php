<?php

namespace App\Filament\Maintenance\Resources\MaintenanceWorkOrderResource\Pages;

use App\Filament\Maintenance\Resources\MaintenanceWorkOrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMaintenanceWorkOrder extends CreateRecord
{
    protected static string $resource = MaintenanceWorkOrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_user_id'] = auth()->id();

        return $data;
    }
}
