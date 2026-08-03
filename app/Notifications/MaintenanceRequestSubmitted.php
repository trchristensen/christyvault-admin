<?php

namespace App\Notifications;

use App\Models\MaintenanceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MaintenanceRequestSubmitted extends Notification
{
    use Queueable;

    public function __construct(public MaintenanceRequest $request) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New maintenance request',
            'message' => $this->request->title,
            'request_id' => $this->request->id,
            'asset_id' => $this->request->asset_id,
            'priority' => $this->request->priority,
            'safety_related' => $this->request->safety_related,
        ];
    }
}
