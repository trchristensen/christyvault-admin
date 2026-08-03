<?php

namespace App\Filament\Maintenance\Resources\MaintenanceVendorResource\Pages;

use App\Filament\Maintenance\Resources\MaintenanceVendorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMaintenanceVendors extends ListRecords
{
    protected static string $resource = MaintenanceVendorResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
