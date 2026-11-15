<?php

namespace App\Enums;

/**
 * Situation d'un frais ou d'un doctorant vis-à-vis de ses paiements.
 */
enum SituationPaiement: string
{
    case Solde = 'solde';
    case Partiel = 'partiel';
    case AVenir = 'a_venir';
    case EnRetard = 'en_retard';
    case RienAPayer = 'rien_a_payer';
    case Annule = 'annule';

    public function libelle(): string
    {
        return match ($this) {
            self::Solde => 'À jour',
            self::Partiel => 'Partiel',
            self::AVenir => 'À payer',
            self::EnRetard => 'En retard',
            self::RienAPayer => 'Rien à payer',
            self::Annule => 'Annulé',
        };
    }
}
