<?php

namespace App\Policies;

use App\Models\MaintenanceWorkOrder;
use App\Models\User;
use App\Policies\Concerns\AuthorizesMaintenance;

class MaintenanceWorkOrderPolicy
{
    use AuthorizesMaintenance;

    public function viewAny(User $user): bool
    {
        return $this->isMaintenanceUser($user);
    }

    public function view(User $user, MaintenanceWorkOrder $workOrder): bool
    {
        return $this->isMaintenanceUser($user);
    }

    public function create(User $user): bool
    {
        return $this->isMaintenanceUser($user);
    }

    public function update(User $user, MaintenanceWorkOrder $workOrder): bool
    {
        return $this->isMaintenanceManager($user)
            || ($user->hasRole('maintenance-technician') && in_array($workOrder->assigned_to_user_id, [null, $user->id], true));
    }

    public function delete(User $user, MaintenanceWorkOrder $workOrder): bool
    {
        return $this->isMaintenanceManager($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->isMaintenanceManager($user);
    }
}
