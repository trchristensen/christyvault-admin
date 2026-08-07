<?php

namespace App\Notifications;

use App\Filament\Maintenance\Resources\VehicleInspectionDefectResource;
use App\Filament\Team\Pages\Schedule;
use App\Models\TripPreTripInspection;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

class TripPreTripDefectReported extends Notification
{
    public function __construct(public TripPreTripInspection $inspection) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $inspection = $this->inspection->loadMissing('trip');
        $trip = $inspection->trip;
        $issueCount = count($inspection->defects ?? []);
        $requiresStop = $inspection->loadMissing('inspectionDefects')->requiresImmediateStop();
        $maintenancePanel = method_exists($notifiable, 'canAccessPanelById')
            && $notifiable->canAccessPanelById('maintenance');
        $reviewUrl = $maintenancePanel
            ? VehicleInspectionDefectResource::getUrl('index', panel: 'maintenance')
            : Schedule::getUrl([
                'date' => $trip->scheduled_date?->toDateString(),
            ], panel: 'team');

        return [
            ...FilamentNotification::make()
                ->title("{$inspection->report_type_label} issue — {$trip->trip_number}")
                ->body(collect([
                    $inspection->driver_name,
                    $issueCount.' '.str('issue')->plural($issueCount),
                    $requiresStop ? 'Driver marked immediate safety concern' : 'Manager operating review requested',
                    str($inspection->defect_notes)->squish()->limit(140)->toString(),
                ])->filter()->join(' · '))
                ->icon('heroicon-o-exclamation-triangle')
                ->iconColor($requiresStop ? 'danger' : 'warning')
                ->actions([
                    Action::make('review')
                        ->label($maintenancePanel ? 'Review defects' : 'Review schedule')
                        ->button()
                        ->url($reviewUrl)
                        ->markAsRead(),
                ])
                ->getDatabaseMessage(),
            'panel' => $maintenancePanel ? 'maintenance' : 'team',
            'trip_id' => $trip->getKey(),
            'trip_pre_trip_inspection_id' => $inspection->getKey(),
        ];
    }
}
