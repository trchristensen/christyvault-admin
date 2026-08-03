<?php

namespace App\Observers;

use App\Models\MaintenanceWorkOrder;
use App\Notifications\MaintenanceWorkOrderAssigned;

class MaintenanceWorkOrderObserver
{
    public function created(MaintenanceWorkOrder $workOrder): void
    {
        $workOrder->assignedTo?->notify(new MaintenanceWorkOrderAssigned($workOrder));
    }

    public function updated(MaintenanceWorkOrder $workOrder): void
    {
        if ($workOrder->wasChanged('assigned_to_user_id') && $workOrder->assigned_to_user_id !== null) {
            $workOrder->assignedTo?->notify(new MaintenanceWorkOrderAssigned($workOrder));
        }
    }
}
