<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait AuthorizesMaintenance
{
    private function isMaintenanceManager(User $user): bool
    {
        return $user->hasRole(['admin', 'super-admin', 'maintenance-manager']);
    }

    private function isMaintenanceUser(User $user): bool
    {
        return $user->hasRole(['admin', 'super-admin', 'maintenance-manager', 'maintenance-technician']);
    }
}
