<?php

namespace App\Notifications;

/** À l'administration, chaque jour s'il y a des retards. */
class RecapitulatifRetards extends NotificationPlateforme
{
    public function __construct(public int $doctorants, public int $montant) {}

    protected function titre(): string
    {
        return 'Retards de paiement';
    }

    protected function message(): string
    {
        return $this->doctorants.' doctorant'.($this->doctorants > 1 ? 's sont' : ' est').' en retard de paiement, pour '.self::ar($this->montant).' au total.';
    }

    protected function lien(): string
    {
        return '/paiements?situation=en_retard';
    }

    protected function categorie(): string
    {
        return 'retards';
    }
}
