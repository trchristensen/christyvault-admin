<?php

namespace App\Filament\Maintenance\Resources\MaintenanceAssetResource\Pages;

use App\Filament\Maintenance\Resources\MaintenanceAssetResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMaintenanceAsset extends EditRecord
{
    protected static string $resource = MaintenanceAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
