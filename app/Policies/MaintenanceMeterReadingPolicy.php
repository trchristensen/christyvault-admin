<?php

namespace App\Policies;

use App\Models\MaintenanceMeterReading;
use App\Models\User;
use App\Policies\Concerns\AuthorizesMaintenance;

class MaintenanceMeterReadingPolicy
{
    use AuthorizesMaintenance;

    public function viewAny(User $user): bool
    {
        return $this->isMaintenanceUser($user);
    }

    public function view(User $user, MaintenanceMeterReading $reading): bool
    {
        return $this->isMaintenanceUser($user);
    }

    public function create(User $user): bool
    {
        return $this->isMaintenanceUser($user);
    }

    public function update(User $user, MaintenanceMeterReading $reading): bool
    {
        return $this->isMaintenanceManager($user);
    }

    public function delete(User $user, MaintenanceMeterReading $reading): bool
    {
        return $this->isMaintenanceManager($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->isMaintenanceManager($user);
    }
}
