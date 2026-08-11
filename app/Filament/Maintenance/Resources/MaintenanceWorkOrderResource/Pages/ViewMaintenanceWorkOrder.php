<?php

namespace App\Filament\Maintenance\Resources\MaintenanceWorkOrderResource\Pages;

use App\Filament\Maintenance\Resources\MaintenanceWorkOrderResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewMaintenanceWorkOrder extends ViewRecord
{
    protected static string $resource = MaintenanceWorkOrderResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->record->number.' — '.$this->record->title;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('print_for_vendor')
                ->label('Print for vendor')
                ->icon('heroicon-o-printer')
                ->url(fn (): string => route('maintenance.work-orders.print', $this->record))
                ->openUrlInNewTab(),
        ];
    }
}
