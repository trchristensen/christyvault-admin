<?php

namespace App\Filament\Maintenance\Resources\MaintenanceAssetResource\Pages;

use App\Filament\Maintenance\Resources\MaintenanceAssetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMaintenanceAssets extends ListRecords
{
    protected static string $resource = MaintenanceAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
