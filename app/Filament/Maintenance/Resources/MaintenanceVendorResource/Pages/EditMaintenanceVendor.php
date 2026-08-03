<?php

namespace App\Filament\Maintenance\Resources\MaintenanceVendorResource\Pages;

use App\Filament\Maintenance\Resources\MaintenanceVendorResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMaintenanceVendor extends EditRecord
{
    protected static string $resource = MaintenanceVendorResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
