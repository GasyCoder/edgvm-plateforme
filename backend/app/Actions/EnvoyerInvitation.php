<?php

namespace App\Actions;

use App\Models\User;
use App\Notifications\InvitationPlateforme;
use Illuminate\Support\Facades\Password;

/**
 * Envoie (ou renvoie) le lien qui permet de choisir son mot de passe et d'accéder à la plateforme.
 */
class EnvoyerInvitation
{
    public function __invoke(User $user): void
    {
        $jeton = Password::broker('invitations')->createToken($user);

        $user->notify(new InvitationPlateforme($jeton));
    }
}
