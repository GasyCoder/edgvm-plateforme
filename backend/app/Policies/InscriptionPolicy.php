<?php

namespace App\Policies;

use App\Models\Doctorant;
use App\Models\Inscription;
use App\Models\User;

/**
 * Seule l'administration inscrit, réinscrit et accorde les dérogations.
 */
class InscriptionPolicy
{
    public function create(User $user, Doctorant $doctorant): bool
    {
        return $user->estSuperAdmin();
    }

    public function update(User $user, Inscription $inscription): bool
    {
        return $user->estSuperAdmin();
    }

    public function delete(User $user, Inscription $inscription): bool
    {
        return $user->estSuperAdmin();
    }
}
