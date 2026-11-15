<?php

namespace App\Policies;

use App\Models\Seance;
use App\Models\User;

/**
 * La scolarité programme les séances ; un doctorant voit celles où il passe,
 * un membre d'équipe celles de ses doctorants ou celles où il intervient.
 */
class SeancePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Seance $seance): bool
    {
        return $user->estSuperAdmin() || Seance::query()->whereKey($seance->id)->visiblesPour($user)->exists();
    }

    public function create(User $user): bool
    {
        return $user->estSuperAdmin();
    }

    public function update(User $user, Seance $seance): bool
    {
        return $user->estSuperAdmin();
    }

    public function delete(User $user, Seance $seance): bool
    {
        return $user->estSuperAdmin();
    }
}
