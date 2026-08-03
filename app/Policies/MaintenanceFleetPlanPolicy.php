<?php

namespace App\Policies;

use App\Models\MaintenanceFleetPlan;
use App\Models\User;
use App\Policies\Concerns\AuthorizesMaintenance;

class MaintenanceFleetPlanPolicy
{
    use AuthorizesMaintenance;

    public function viewAny(User $user): bool
    {
        return $this->isMaintenanceUser($user);
    }

    public function view(User $user, MaintenanceFleetPlan $plan): bool
    {
        return $this->isMaintenanceUser($user);
    }

    public function create(User $user): bool
    {
        return $this->isMaintenanceManager($user);
    }

    public function update(User $user, MaintenanceFleetPlan $plan): bool
    {
        return $this->isMaintenanceManager($user);
    }

    public function delete(User $user, MaintenanceFleetPlan $plan): bool
    {
        return $this->isMaintenanceManager($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->isMaintenanceManager($user);
    }
}
