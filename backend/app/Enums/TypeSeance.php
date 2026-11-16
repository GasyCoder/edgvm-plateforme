<?php

namespace App\Enums;

enum TypeSeance: string
{
    case Seminaire = 'seminaire';
    case EtatAvancement = 'etat_avancement';
    case PresentationProjet = 'presentation_projet';
    case Autre = 'autre';

    public function libelle(): string
    {
        return match ($this) {
            self::Seminaire => 'Séminaire doctoral',
            self::EtatAvancement => "État d'avancement",
            self::PresentationProjet => 'Présentation de projet de thèse',
            self::Autre => 'Autre communication',
        };
    }
}
