<?php

namespace App\Support;

use App\Models\Reglage;
use Illuminate\Support\Str;

/**
 * Jeton « Bearer » de l'API publique lue par le site vitrine. Seule son empreinte est gardée :
 * le jeton en clair n'est montré qu'une fois, au moment où il est créé.
 */
class JetonApiPublique
{
    private const CLE = 'api_publique_jeton';

    private const CLE_DATE = 'api_publique_jeton_cree_le';

    public static function generer(): string
    {
        $jeton = 'edgvm_'.Str::random(48);
        Reglage::definir(self::CLE, hash('sha256', $jeton));
        Reglage::definir(self::CLE_DATE, now()->toIso8601String());

        return $jeton;
    }

    public static function revoquer(): void
    {
        Reglage::definir(self::CLE, null);
        Reglage::definir(self::CLE_DATE, null);
    }

    public static function estActif(): bool
    {
        return filled(Reglage::valeur(self::CLE));
    }

    public static function creeLe(): ?string
    {
        return Reglage::valeur(self::CLE_DATE);
    }

    public static function verifier(?string $jeton): bool
    {
        $empreinte = Reglage::valeur(self::CLE);

        return filled($empreinte) && filled($jeton) && hash_equals($empreinte, hash('sha256', $jeton));
    }
}
