<?php

namespace App\Actions;

use App\Models\Inscription;
use App\Models\TypeFrais;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * À chaque inscription, crée les frais obligatoires (droits d'inscription...) au tarif du niveau.
 */
class AppliquerFraisInscription
{
    public function __invoke(Inscription $inscription, ?User $par = null): int
    {
        $debut = Carbon::parse($inscription->date_inscription ?? now())->startOfDay();
        $crees = 0;

        foreach (TypeFrais::query()->where('actif', true)->where('obligatoire', true)->orderBy('ordre')->get() as $type) {
            $montant = $type->montantPour($inscription);

            if ($montant <= 0) {
                continue;
            }

            foreach ($type->planifier($montant, $debut, $inscription->anneeUniversitaire) as $tranche) {
                $inscription->doctorant->frais()->create([
                    'type_frais_id' => $type->id,
                    'annee_universitaire_id' => $inscription->annee_universitaire_id,
                    'inscription_id' => $inscription->id,
                    'libelle' => TypeFrais::libelleTranche($type->libelle, $tranche['tranche'], $tranche['tranches']),
                    ...$tranche,
                    'cree_par' => $par?->id,
                ]);
                $crees++;
            }
        }

        return $crees;
    }
}
