<?php

namespace App\Actions;

use App\Models\FraisDoctorant;
use App\Models\TypeFrais;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Recalcule au nouveau tarif les frais de ce type pas encore soldés : montant du niveau de l'inscription liée
 * (ou montant unique) et échéance selon la règle du type (fin d'année ou délai). Un frais découpé autrement
 * (nombre de tranches changé) est laissé tel quel. Jamais modifiés : frais soldés, annulés, ajustés à la main, ou déjà payés au-delà du nouveau montant.
 * Un tarif à 0 annule les frais concernés encore non payés.
 */
class AppliquerNouveauTarif
{
    /**
     * @return array{modifies: int, annules: int, ignores: int}
     */
    public function __invoke(TypeFrais $type): array
    {
        $resultat = ['modifies' => 0, 'annules' => 0, 'ignores' => 0];

        DB::transaction(function () use ($type, &$resultat): void {
            self::concernes($type)->with(['inscription', 'anneeUniversitaire'])->lockForUpdate()->get()->each(function (FraisDoctorant $frais) use ($type, &$resultat): void {
                $total = $type->montantPour($frais->inscription);
                $debut = $frais->inscription?->date_inscription ?? $frais->created_at;
                $plan = $type->planifier($total, $debut, $frais->anneeUniversitaire);
                $prevu = $plan[($frais->tranche ?? 1) - 1] ?? null;

                if (! $prevu || ($frais->tranches ?? 1) !== count($plan)) {
                    $resultat['ignores']++;

                    return;
                }

                $nouveau = $total === 0 ? 0 : $prevu['montant'];

                if ($nouveau === $frais->montant && $prevu['echeance'] === $frais->echeance?->toDateString()) {
                    return;
                }

                if ($nouveau === 0 && $frais->montant_paye === 0) {
                    $frais->update(['annule_le' => now(), 'motif_annulation' => 'Tarif à 0 dans les paramètres']);
                    $resultat['annules']++;

                    return;
                }

                if ($nouveau < max(1, $frais->montant_paye)) {
                    $resultat['ignores']++;

                    return;
                }

                $frais->update(['montant' => $nouveau, 'echeance' => $prevu['echeance']]);
                $resultat['modifies']++;
            });
        });

        return $resultat;
    }

    /**
     * Frais que le changement de tarif peut toucher.
     *
     * @return Builder<FraisDoctorant>
     */
    public static function concernes(TypeFrais $type): Builder
    {
        return FraisDoctorant::query()->where('type_frais_id', $type->id)->impayes()->whereNull('motif_ajustement');
    }
}
