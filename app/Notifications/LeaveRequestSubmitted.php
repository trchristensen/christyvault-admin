<?php

namespace App\Notifications;

use App\Filament\Resources\LeaveRequestResource;
use App\Models\LeaveRequest;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

class LeaveRequestSubmitted extends Notification
{
    public function __construct(public LeaveRequest $leaveRequest) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $employee = $this->leaveRequest->employee?->name ?? 'An employee';
        $type = str($this->leaveRequest->type)->headline()->toString();

        return [
            ...FilamentNotification::make()
                ->title("New time-off request from {$employee}")
                ->body("{$type} · {$this->leaveRequest->dateSummary()}")
                ->icon('heroicon-o-calendar-days')
                ->iconColor('warning')
                ->actions([
                    Action::make('review')
                        ->label('Review request')
                        ->button()
                        ->url(LeaveRequestResource::getUrl('edit', [
                            'record' => $this->leaveRequest,
                        ], panel: 'admin'))
                        ->markAsRead(),
                ])
                ->getDatabaseMessage(),
            'panel' => 'admin',
            'leave_request_id' => $this->leaveRequest->getKey(),
        ];
    }
}
