<?php

namespace App\Enums;

enum TypeInscription: string
{
    case Premiere = 'premiere';
    case Reinscription = 'reinscription';

    public function libelle(): string
    {
        return match ($this) {
            self::Premiere => 'Première inscription',
            self::Reinscription => 'Réinscription',
        };
    }
}
