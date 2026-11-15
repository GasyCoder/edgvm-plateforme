<?php

namespace App\Notifications;

use App\Models\Paiement;
use App\Models\User;

/** Aux autres administrateurs : un versement vient d'être saisi. */
class PaiementEnregistre extends NotificationPlateforme
{
    public function __construct(public Paiement $paiement, public User $par) {}

    protected function titre(): string
    {
        return 'Paiement enregistré';
    }

    protected function message(): string
    {
        return $this->paiement->doctorant->nomComplet().' : '.self::ar($this->paiement->montant).' ('.$this->paiement->mode->libelle().'), reçu n° '
            .$this->paiement->numero_recu.', saisi par '.$this->par->name.'.';
    }

    protected function lien(): string
    {
        return '/doctorants/'.$this->paiement->doctorant->uuid.'?section=paiements';
    }

    protected function categorie(): string
    {
        return 'paiement';
    }
}
