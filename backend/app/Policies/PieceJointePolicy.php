<?php

namespace App\Policies;

use App\Models\Doctorant;
use App\Models\PieceJointe;
use App\Models\User;

/**
 * Administration : tout. Doctorant : ses pièces (ajout, et suppression de celles qu'il a déposées).
 * Membre d'équipe : consultation des pièces de ses doctorants, sauf la pièce d'identité.
 */
class PieceJointePolicy
{
    public function viewAny(User $user, Doctorant $doctorant): bool
    {
        return $user->can('view', $doctorant);
    }

    public function view(User $user, PieceJointe $piece): bool
    {
        if ($user->estMembreEad() && $piece->categorie->estPersonnelle()) {
            return false;
        }

        return $user->can('view', $piece->doctorant);
    }

    public function create(User $user, Doctorant $doctorant): bool
    {
        return $user->estSuperAdmin() || $doctorant->user_id === $user->id;
    }

    public function delete(User $user, PieceJointe $piece): bool
    {
        return $user->estSuperAdmin() || ($piece->doctorant->user_id === $user->id && $piece->ajoute_par === $user->id);
    }
}
