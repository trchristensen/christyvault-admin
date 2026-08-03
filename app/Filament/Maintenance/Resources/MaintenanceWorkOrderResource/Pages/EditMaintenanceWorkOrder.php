<?php

namespace App\Filament\Maintenance\Resources\MaintenanceWorkOrderResource\Pages;

use App\Filament\Maintenance\Resources\MaintenanceWorkOrderResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditMaintenanceWorkOrder extends EditRecord
{
    protected static string $resource = MaintenanceWorkOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print_for_vendor')
                ->label('Print for vendor')
                ->icon('heroicon-o-printer')
                ->url(fn (): string => route('maintenance.work-orders.print', $this->record))
                ->openUrlInNewTab(),
        ];
    }
}
