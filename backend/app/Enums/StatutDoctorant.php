<?php

namespace App\Enums;

enum StatutDoctorant: string
{
    case Actif = 'actif';
    case Suspendu = 'suspendu';
    case Abandon = 'abandon';
    case Diplome = 'diplome';

    public function libelle(): string
    {
        return match ($this) {
            self::Actif => 'Actif',
            self::Suspendu => 'Suspendu',
            self::Abandon => 'Abandon',
            self::Diplome => 'Diplômé',
        };
    }
}
