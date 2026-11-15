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

    /**
     * L'administration voit toutes les fiches ; un membre d'équipe, celles de ses équipes.
     */
    public function view(User $user, EquipeAccueil $equipeAccueil): bool
    {
        return $user->estSuperAdmin() || ($user->estMembreEad() && in_array($equipeAccueil->id, array_map('intval', $user->idsEquipesSuivies()), true));
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
