<?php

namespace App\Enums;

enum StatutSeance: string
{
    case Brouillon = 'brouillon';
    case Publiee = 'publiee';
    case Annulee = 'annulee';

    public function libelle(): string
    {
        return match ($this) {
            self::Brouillon => 'En préparation',
            self::Publiee => 'Convocations envoyées',
            self::Annulee => 'Annulée',
        };
    }
}
