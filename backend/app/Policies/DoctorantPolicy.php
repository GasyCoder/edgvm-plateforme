<?php

namespace App\Policies;

use App\Models\Doctorant;
use App\Models\User;

/**
 * Administration : tout. Membre d'équipe : les doctorants de ses équipes. Doctorant : son propre dossier.
 */
class DoctorantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->estSuperAdmin() || $user->estMembreEad();
    }

    public function view(User $user, Doctorant $doctorant): bool
    {
        if ($user->estSuperAdmin()) {
            return true;
        }

        if ($user->estMembreEad()) {
            return in_array($doctorant->equipe_accueil_id, $user->idsEquipesSuivies(), true);
        }

        return $doctorant->user_id === $user->id;
    }

    /**
     * Données personnelles sensibles (naissance, pièce d'identité, adresse, observation).
     */
    public function viewDonneesPersonnelles(User $user, Doctorant $doctorant): bool
    {
        return $user->estSuperAdmin() || $doctorant->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->estSuperAdmin();
    }

    public function update(User $user, Doctorant $doctorant): bool
    {
        return $user->estSuperAdmin();
    }

    public function delete(User $user, Doctorant $doctorant): bool
    {
        return false;
    }
}
