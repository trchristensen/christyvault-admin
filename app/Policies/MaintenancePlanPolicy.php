<?php

namespace App\Policies;

use App\Models\MaintenancePlan;
use App\Models\User;
use App\Policies\Concerns\AuthorizesMaintenance;

class MaintenancePlanPolicy
{
    use AuthorizesMaintenance;

    public function viewAny(User $user): bool
    {
        return $this->isMaintenanceUser($user);
    }

    public function view(User $user, MaintenancePlan $plan): bool
    {
        return $this->isMaintenanceUser($user);
    }

    public function create(User $user): bool
    {
        return $this->isMaintenanceManager($user);
    }

    public function update(User $user, MaintenancePlan $plan): bool
    {
        return $this->isMaintenanceManager($user);
    }

    public function delete(User $user, MaintenancePlan $plan): bool
    {
        return $this->isMaintenanceManager($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->isMaintenanceManager($user);
    }
}
