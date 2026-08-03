<?php

namespace App\Filament\Maintenance\Resources\MaintenanceFleetPlanResource\Pages;

use App\Filament\Maintenance\Resources\MaintenanceFleetPlanResource;
use App\Services\Maintenance\MaintenanceFleetPlanScheduler;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
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
        return [
            Action::make('schedule_service_now')
                ->label('Schedule fleet service now')
                ->icon('heroicon-o-calendar-days')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Create work orders for every included asset?')
                ->modalDescription('Use this only when you intentionally want to service the fleet early. Normal 250-hour service is generated automatically from meter readings.')
                ->modalSubmitActionLabel('Create all work orders')
                ->action(function (): void {
                    $run = app(MaintenanceFleetPlanScheduler::class)->generate($this->record, force: true);
                    $count = $run?->workOrders()->count() ?? 0;
                    Notification::make()
                        ->title($run ? "Created {$count} fleet work orders" : 'An open fleet service already exists or no assets are included')
                        ->color($run ? 'success' : 'warning')
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
