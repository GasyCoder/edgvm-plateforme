<?php

namespace App\Support;

use App\Enums\StatutSeance;
use App\Models\Passage;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Qui est déjà pris, et quand : une personne (modérateur, jury) ou un doctorant ne peut pas être
 * à deux passages qui se chevauchent, dans la même séance ou dans une autre (séances annulées exceptées).
 */
class Disponibilites
{
    /**
     * Passages qui chevauchent [début, fin[ pour cette personne.
     *
     * @param  array<int, int>  $saufPassages
     * @return Collection<int, Passage>
     */
    public static function occupationsMembre(int $membreId, CarbonInterface $debut, CarbonInterface $fin, array $saufPassages = []): Collection
    {
        return self::chevauchements($debut, $fin, $saufPassages)
            ->whereHas('intervenants', fn (Builder $q) => $q->whereKey($membreId))
            ->get();
    }

    /**
     * @param  array<int, int>  $saufPassages
     * @return Collection<int, Passage>
     */
    public static function occupationsDoctorant(int $doctorantId, CarbonInterface $debut, CarbonInterface $fin, array $saufPassages = []): Collection
    {
        return self::chevauchements($debut, $fin, $saufPassages)->where('doctorant_id', $doctorantId)->get();
    }

    /**
     * « Séminaire doctoral, 20/11 de 09:00 à 09:30 (RABE Fanja) »
     */
    public static function decrire(Passage $passage): string
    {
        $passage->loadMissing(['seance', 'doctorant']);

        return $passage->seance->titre.', '.$passage->debut->format('d/m').' de '.$passage->debut->format('H:i').' à '.$passage->fin->format('H:i')
            .' ('.$passage->doctorant->nomComplet().')';
    }

    /**
     * @param  array<int, int>  $saufPassages
     * @return Builder<Passage>
     */
    private static function chevauchements(CarbonInterface $debut, CarbonInterface $fin, array $saufPassages): Builder
    {
        return Passage::query()
            ->with(['seance', 'doctorant'])
            ->whereNotIn('passages.id', $saufPassages)
            ->where('debut', '<', $fin)
            ->where('fin', '>', $debut)
            ->whereHas('seance', fn (Builder $q) => $q->where('statut', '!=', StatutSeance::Annulee->value));
    }
}
