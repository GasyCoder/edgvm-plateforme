<?php

namespace App\Notifications;

use Carbon\CarbonInterface;

/** Au doctorant en retard (au plus une fois par semaine), ou envoyé à la main depuis la situation des paiements. */
class RappelPaiement extends NotificationPlateforme
{
    protected bool $parEmail = true;

    public function __construct(public int $enRetard, public int $reste, public ?CarbonInterface $prochaineEcheance = null) {}

    protected function titre(): string
    {
        return 'Rappel de paiement';
    }

    protected function message(): string
    {
        if ($this->enRetard > 0) {
            return self::ar($this->enRetard).' de frais ont dépassé leur échéance (reste total à payer : '.self::ar($this->reste).'). '
                .'Merci de régulariser auprès de la scolarité de l\'École.';
        }

        return 'Il vous reste '.self::ar($this->reste).' à payer'
            .($this->prochaineEcheance ? ', prochaine échéance le '.$this->prochaineEcheance->locale('fr')->translatedFormat('d F Y') : '')
            .'. Vous pouvez régler en un ou plusieurs versements auprès de la scolarité de l\'École.';
    }

    protected function lien(): string
    {
        return '/paiements';
    }

    protected function categorie(): string
    {
        return 'rappel';
    }
}
