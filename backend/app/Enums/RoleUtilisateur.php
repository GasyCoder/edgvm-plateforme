<?php

namespace App\Enums;

enum RoleUtilisateur: string
{
    case SuperAdmin = 'super_admin';
    case MembreEad = 'membre_ead';
    case Doctorant = 'doctorant';

    public function libelle(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Administration',
            self::MembreEad => "Membre d'équipe d'accueil",
            self::Doctorant => 'Doctorant',
        };
    }
}
