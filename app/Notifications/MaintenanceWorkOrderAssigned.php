<?php

namespace App\Notifications;

use App\Models\MaintenanceWorkOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MaintenanceWorkOrderAssigned extends Notification
{
    use Queueable;

    public function __construct(public MaintenanceWorkOrder $workOrder) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => "Maintenance work order {$this->workOrder->number}",
            'message' => $this->workOrder->title,
            'work_order_id' => $this->workOrder->id,
            'asset_id' => $this->workOrder->asset_id,
            'priority' => $this->workOrder->priority,
        ];
    }
}
