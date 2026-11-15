<?php

namespace App\Policies;

use App\Models\Doctorant;
use App\Models\Paiement;
use App\Models\User;

/**
 * Seule l'administration encaisse, applique des frais et annule. Le doctorant consulte ses frais, paiements et reçus.
 * Un membre d'équipe ne voit que la situation (à jour ou en retard), pas le détail.
 */
class PaiementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->estSuperAdmin();
    }

    public function voirFinances(User $user, Doctorant $doctorant): bool
    {
        return $user->estSuperAdmin() || $doctorant->user_id === $user->id;
    }

    public function view(User $user, Paiement $paiement): bool
    {
        return $user->estSuperAdmin() || ($paiement->doctorant->user_id === $user->id && ! $paiement->estAnnule());
    }

    public function create(User $user): bool
    {
        return $user->estSuperAdmin();
    }

    public function annuler(User $user, Paiement $paiement): bool
    {
        return $user->estSuperAdmin() && ! $paiement->estAnnule();
    }
}
