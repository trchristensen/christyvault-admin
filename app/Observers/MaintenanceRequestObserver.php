<?php

namespace App\Observers;

use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Notifications\MaintenanceRequestSubmitted;
use Illuminate\Support\Facades\Notification;

class MaintenanceRequestObserver
{
    public function created(MaintenanceRequest $request): void
    {
        $recipients = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['admin', 'super-admin', 'maintenance-manager']))
            ->get();

        Notification::send($recipients, new MaintenanceRequestSubmitted($request));
    }
}
