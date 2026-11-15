<?php

namespace App\Policies;

use App\Models\User;

/**
 * Gestion des comptes : réservée à l'administration.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->estSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->estSuperAdmin();
    }

    /**
     * Un administrateur ne peut pas se retirer ses propres droits ni se désactiver.
     */
    public function update(User $user, User $compte): bool
    {
        return $user->estSuperAdmin();
    }
}
