<?php

namespace App\Enums;

/**
 * Rôle d'une personne dans une équipe d'accueil.
 */
enum FonctionMembre: string
{
    case Responsable = 'responsable';
    case Encadrant = 'encadrant';
    case Membre = 'membre';

    public function libelle(): string
    {
        return match ($this) {
            self::Responsable => "Responsable de l'équipe",
            self::Encadrant => 'Encadrant',
            self::Membre => 'Membre',
        };
    }

    /** Ordre d'affichage : responsable, puis encadrants, puis membres. */
    public function rang(): int
    {
        return match ($this) {
            self::Responsable => 0,
            self::Encadrant => 1,
            self::Membre => 2,
        };
    }
}
