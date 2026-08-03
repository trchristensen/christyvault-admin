<?php

namespace App\Policies;

use App\Models\MaintenanceAsset;
use App\Models\User;
use App\Policies\Concerns\AuthorizesMaintenance;

class MaintenanceAssetPolicy
{
    use AuthorizesMaintenance;

    public function viewAny(User $user): bool
    {
        return $this->isMaintenanceUser($user);
    }

    public function view(User $user, MaintenanceAsset $asset): bool
    {
        return $this->isMaintenanceUser($user);
    }

    public function create(User $user): bool
    {
        return $this->isMaintenanceManager($user);
    }

    public function update(User $user, MaintenanceAsset $asset): bool
    {
        return $this->isMaintenanceManager($user);
    }

    public function delete(User $user, MaintenanceAsset $asset): bool
    {
        return $this->isMaintenanceManager($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->isMaintenanceManager($user);
    }
}
