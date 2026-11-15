<?php

namespace App\Notifications;

use App\Models\Paiement;
use App\Support\SituationFinanciere;
use App\Support\TableauCourriel;
use Illuminate\Support\HtmlString;

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

    protected function introductionCourriel(): HtmlString
    {
        return TableauCourriel::phrase('Votre versement de :montant a bien été enregistré par la scolarité (reçu :recu). Le reçu est disponible sur la plateforme.', [
            'montant' => self::ar($this->paiement->montant),
            'recu' => 'n° '.$this->paiement->numero_recu,
        ]);
    }

    /**
     * @return array<string, ?string>
     */
    protected function detailsCourriel(): array
    {
        $reste = $this->paiement->reste_apres ?? SituationFinanciere::pour($this->paiement->doctorant)['reste'];

        return [
            'Date du versement' => $this->paiement->date_paiement->format('d/m/Y'),
            'Mode' => $this->paiement->mode->libelle(),
            'Reste à payer' => $reste > 0 ? self::ar($reste) : 'Rien, vous êtes à jour',
        ];
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
