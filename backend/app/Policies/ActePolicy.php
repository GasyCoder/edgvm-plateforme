<?php

namespace App\Policies;

use App\Models\Acte;
use App\Models\User;

/**
 * Seule l'administration délivre et annule. Chacun consulte les actes des doctorants qu'il voit ;
 * un doctorant télécharge ses actes valides.
 */
class ActePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Acte $acte): bool
    {
        if ($user->estDoctorant() && $acte->estAnnule()) {
            return false;
        }

        return $user->can('view', $acte->doctorant);
    }

    public function create(User $user): bool
    {
        return $user->estSuperAdmin();
    }

    public function annuler(User $user, Acte $acte): bool
    {
        return $user->estSuperAdmin() && ! $acte->estAnnule();
    }
}
