<?php

namespace App\Policies;

use App\Models\User;
use SpykApp\PasswordlessLogin\Models\MagicLoginToken;

class MagicLoginTokenPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view magic login tokens');
    }

    public function view(User $user, MagicLoginToken $token): bool
    {
        return $user->can('view magic login tokens');
    }

    public function create(User $user): bool
    {
        return $user->can('manage magic login tokens');
    }

    public function update(User $user, MagicLoginToken $token): bool
    {
        return $user->can('manage magic login tokens');
    }

    public function delete(User $user, MagicLoginToken $token): bool
    {
        return $user->can('manage magic login tokens');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('manage magic login tokens');
    }
}
