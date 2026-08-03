<?php

namespace App\Policies;

use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Policies\Concerns\AuthorizesMaintenance;

class MaintenanceRequestPolicy
{
    use AuthorizesMaintenance;

    public function viewAny(User $user): bool
    {
        return $this->isMaintenanceUser($user);
    }

    public function view(User $user, MaintenanceRequest $request): bool
    {
        return $this->isMaintenanceUser($user);
    }

    public function create(User $user): bool
    {
        return $this->isMaintenanceUser($user);
    }

    public function update(User $user, MaintenanceRequest $request): bool
    {
        return $this->isMaintenanceManager($user);
    }

    public function delete(User $user, MaintenanceRequest $request): bool
    {
        return $this->isMaintenanceManager($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->isMaintenanceManager($user);
    }
}
