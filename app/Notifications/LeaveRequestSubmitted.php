<?php

namespace App\Notifications;

use App\Filament\Resources\LeaveRequestResource;
use App\Filament\Team\Resources\LeaveRequestResource as TeamLeaveRequestResource;
use App\Models\LeaveRequest;
use App\Models\User;
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
        $usesAdminPanel = $notifiable instanceof User && $notifiable->canAccessPanelById('admin');
        $resource = $usesAdminPanel ? LeaveRequestResource::class : TeamLeaveRequestResource::class;
        $panel = $usesAdminPanel ? 'admin' : 'team';

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
                        ->url($resource::getUrl('edit', [
                            'record' => $this->leaveRequest,
                        ], panel: $panel))
                        ->markAsRead(),
                ])
                ->getDatabaseMessage(),
            'panel' => $panel,
            'leave_request_id' => $this->leaveRequest->getKey(),
        ];
    }
}
