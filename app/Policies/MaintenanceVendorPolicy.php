<?php

namespace App\Policies;

use App\Models\MaintenanceVendor;
use App\Models\User;
use App\Policies\Concerns\AuthorizesMaintenance;

class MaintenanceVendorPolicy
{
    use AuthorizesMaintenance;

    public function viewAny(User $user): bool
    {
        return $this->isMaintenanceUser($user);
    }

    public function view(User $user, MaintenanceVendor $vendor): bool
    {
        return $this->isMaintenanceUser($user);
    }

    public function create(User $user): bool
    {
        return $this->isMaintenanceManager($user);
    }

    public function update(User $user, MaintenanceVendor $vendor): bool
    {
        return $this->isMaintenanceManager($user);
    }

    public function delete(User $user, MaintenanceVendor $vendor): bool
    {
        return $this->isMaintenanceManager($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->isMaintenanceManager($user);
    }
}
