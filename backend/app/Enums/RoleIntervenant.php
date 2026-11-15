<?php

namespace App\Enums;

enum RoleIntervenant: string
{
    case Moderateur = 'moderateur';
    case Jury = 'jury';

    public function libelle(): string
    {
        return match ($this) {
            self::Moderateur => 'Modérateur',
            self::Jury => 'Jury',
        };
    }
}
