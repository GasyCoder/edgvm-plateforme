<?php

namespace App\Enums;

enum CategoriePiece: string
{
    case Diplome = 'diplome';
    case PieceIdentite = 'piece_identite';
    case Attestation = 'attestation';
    case Publication = 'publication';
    case Autre = 'autre';

    public function libelle(): string
    {
        return match ($this) {
            self::Diplome => 'Diplôme',
            self::PieceIdentite => "Pièce d'identité",
            self::Attestation => 'Attestation',
            self::Publication => 'Publication',
            self::Autre => 'Autre document',
        };
    }

    /**
     * Les pièces d'identité ne sont visibles que par l'administration et le doctorant.
     */
    public function estPersonnelle(): bool
    {
        return $this === self::PieceIdentite;
    }
}
