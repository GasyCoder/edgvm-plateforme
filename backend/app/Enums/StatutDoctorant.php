<?php

namespace App\Enums;

/**
 * Un « candidat » veut entrer à l'École : il présente son projet de thèse ; admis, il devient doctorant « actif ».
 */
enum StatutDoctorant: string
{
    case Candidat = 'candidat';
    case Actif = 'actif';
    case Suspendu = 'suspendu';
    case Abandon = 'abandon';
    case Diplome = 'diplome';

    public function libelle(): string
    {
        return match ($this) {
            self::Candidat => 'Candidat',
            self::Actif => 'Actif',
            self::Suspendu => 'Suspendu',
            self::Abandon => 'Abandon',
            self::Diplome => 'Diplômé',
        };
    }
}
