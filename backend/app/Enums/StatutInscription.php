<?php

namespace App\Enums;

enum StatutInscription: string
{
    case EnAttente = 'en_attente';
    case Validee = 'validee';
    case Annulee = 'annulee';

    public function libelle(): string
    {
        return match ($this) {
            self::EnAttente => 'En attente',
            self::Validee => 'Validée',
            self::Annulee => 'Annulée',
        };
    }
}
