<?php

namespace App\Filament\Maintenance\Resources\MaintenanceFleetPlanResource\Pages;

use App\Filament\Maintenance\Resources\MaintenanceFleetPlanResource;
use App\Services\Maintenance\MaintenanceFleetPlanScheduler;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMaintenanceFleetPlan extends EditRecord
{
    protected static string $resource = MaintenanceFleetPlanResource::class;

    protected function afterSave(): void
    {
        app(MaintenanceFleetPlanScheduler::class)->syncMatchingAssets($this->record);
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
