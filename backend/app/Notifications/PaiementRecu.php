<?php

namespace App\Notifications;

use App\Models\Paiement;
use App\Support\SituationFinanciere;

/** Au doctorant : son versement est enregistré. */
class PaiementRecu extends NotificationPlateforme
{
    protected bool $parEmail = true;

    public function __construct(public Paiement $paiement) {}

    protected function titre(): string
    {
        return 'Paiement reçu';
    }

    protected function message(): string
    {
        $reste = $this->paiement->reste_apres ?? SituationFinanciere::pour($this->paiement->doctorant)['reste'];

        return 'Votre paiement de '.self::ar($this->paiement->montant).' est enregistré (reçu n° '.$this->paiement->numero_recu.'). '
            .($reste > 0 ? 'Reste à payer : '.self::ar($reste).'.' : 'Vous êtes à jour de vos paiements.');
    }

    protected function lien(): string
    {
        return '/paiements';
    }

    protected function categorie(): string
    {
        return 'paiement';
    }
}
