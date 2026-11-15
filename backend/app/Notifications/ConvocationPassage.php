<?php

namespace App\Notifications;

use App\Enums\RoleIntervenant;
use App\Models\MembreEquipe;
use App\Models\Passage;
use App\Support\GabaritActe;
use App\Support\TableauCourriel;
use Illuminate\Support\HtmlString;

/** Au doctorant : son passage à une séance (date, heure, lieu, modérateurs et jury). */
class ConvocationPassage extends NotificationPlateforme
{
    protected bool $parEmail = true;

    public function __construct(public Passage $passage) {}

    protected function titre(): string
    {
        return 'Convocation : '.$this->passage->seance->type->libelle();
    }

    protected function message(): string
    {
        $seance = $this->passage->seance;
        $noms = fn (RoleIntervenant $role) => $this->passage->intervenants
            ->filter(fn (MembreEquipe $membre) => $membre->pivot->role === $role->value)
            ->map->nomAvecTitre()->implode(', ');

        return 'Votre passage à « '.$seance->titre.' » est prévu le '.GabaritActe::dateLongue($this->passage->debut)
            .' de '.$this->passage->debut->format('H:i').' à '.$this->passage->fin->format('H:i')
            .($seance->lieu ? ', '.$seance->lieu : '').'.'
            .(($m = $noms(RoleIntervenant::Moderateur)) ? ' Modération : '.$m.'.' : '')
            .(($j = $noms(RoleIntervenant::Jury)) ? ' Jury : '.$j.'.' : '');
    }

    protected function introductionCourriel(): HtmlString
    {
        $seance = $this->passage->seance;

        return TableauCourriel::phrase(
            $seance->lieu
                ? 'Vous êtes convoqué(e) à la séance :seance le :date, de :horaire, :lieu, pour présenter vos travaux.'
                : 'Vous êtes convoqué(e) à la séance :seance le :date, de :horaire, pour présenter vos travaux.',
            [
                'seance' => $seance->intitule(),
                'date' => GabaritActe::dateLongue($this->passage->debut),
                'horaire' => $this->passage->debut->format('H:i').' à '.$this->passage->fin->format('H:i'),
                'lieu' => $seance->lieu,
            ],
        );
    }

    /**
     * @return array<string, ?string>
     */
    protected function detailsCourriel(): array
    {
        $noms = fn (RoleIntervenant $role) => $this->passage->intervenants
            ->filter(fn (MembreEquipe $membre) => $membre->pivot->role === $role->value)
            ->map->nomAvecTitre()->implode(', ');

        return [
            'Sujet présenté' => $this->passage->sujet ?? $this->passage->doctorant->sujet_these,
            'Modération' => $noms(RoleIntervenant::Moderateur),
            'Jury' => $noms(RoleIntervenant::Jury),
        ];
    }

    protected function lien(): string
    {
        return '/seances/'.$this->passage->seance_id;
    }

    protected function categorie(): string
    {
        return 'seance';
    }
}
