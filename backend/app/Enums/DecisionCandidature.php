<?php

namespace App\Enums;

/**
 * Décision du jury après la présentation du projet de thèse d'un candidat.
 */
enum DecisionCandidature: string
{
    case Admis = 'admis';
    case Ajourne = 'ajourne';
    case NonAdmis = 'non_admis';

    public function libelle(): string
    {
        return match ($this) {
            self::Admis => 'Admis',
            self::Ajourne => 'Ajourné',
            self::NonAdmis => 'Non admis',
        };
    }
}
