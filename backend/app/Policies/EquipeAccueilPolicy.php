<?php

namespace App\Policies;

use App\Models\EquipeAccueil;
use App\Models\User;

class EquipeAccueilPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->estSuperAdmin() || $user->estMembreEad();
    }

    public function create(User $user): bool
    {
        return $user->estSuperAdmin();
    }

    public function update(User $user, EquipeAccueil $equipeAccueil): bool
    {
        return $user->estSuperAdmin();
    }

    public function delete(User $user, EquipeAccueil $equipeAccueil): bool
    {
        return $user->estSuperAdmin();
    }
}
