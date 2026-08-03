<?php

namespace App\Filament\Maintenance\Resources\MaintenancePlanResource\Pages;

use App\Filament\Maintenance\Resources\MaintenancePlanResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMaintenancePlan extends EditRecord
{
    protected static string $resource = MaintenancePlanResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
