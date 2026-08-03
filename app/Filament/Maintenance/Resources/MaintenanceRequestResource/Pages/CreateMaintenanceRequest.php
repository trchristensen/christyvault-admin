<?php

namespace App\Filament\Maintenance\Resources\MaintenanceRequestResource\Pages;

use App\Filament\Maintenance\Resources\MaintenanceRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMaintenanceRequest extends CreateRecord
{
    protected static string $resource = MaintenanceRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['requested_by_user_id'] = auth()->id();
        $data['requester_name'] ??= auth()->user()?->name;
        $data['submitted_at'] = now();

        return $data;
    }
}
