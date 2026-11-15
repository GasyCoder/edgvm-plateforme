<?php

namespace App\Notifications;

use App\Models\FraisDoctorant;

/** Au doctorant : un nouveau frais est à régler. */
class NouveauFrais extends NotificationPlateforme
{
    protected bool $parEmail = true;

    public function __construct(public FraisDoctorant $frais) {}

    protected function titre(): string
    {
        return 'Nouveau frais à régler';
    }

    protected function message(): string
    {
        return $this->frais->libelle.' : '.self::ar($this->frais->montant)
            .($this->frais->echeance ? ', à régler avant le '.$this->frais->echeance->locale('fr')->translatedFormat('d F Y') : '').'.';
    }

    protected function lien(): string
    {
        return '/paiements';
    }

    protected function categorie(): string
    {
        return 'frais';
    }
}
