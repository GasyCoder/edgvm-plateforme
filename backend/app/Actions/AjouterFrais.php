<?php

namespace App\Actions;

use App\Enums\StatutInscription;
use App\Models\AnneeUniversitaire;
use App\Models\Doctorant;
use App\Models\FraisDoctorant;
use App\Models\TypeFrais;
use App\Models\User;
use App\Notifications\NouveauFrais;
use Illuminate\Support\Collection;

/**
 * Applique un frais (tee-shirt, cotisation...) à un ou plusieurs doctorants.
 * Montant : celui saisi, sinon le tarif du type selon le niveau de l'année. Pas de doublon pour un même type et une même année.
 */
class AjouterFrais
{
    /**
     * @param  Collection<int, Doctorant>  $doctorants
     * @return array{crees: Collection<int, FraisDoctorant>, ignores: array<int, array{doctorant: string, message: string}>}
     */
    public function __invoke(Collection $doctorants, ?TypeFrais $type, ?string $libelle, ?int $montant, ?AnneeUniversitaire $annee, ?string $echeance, User $par): array
    {
        $crees = collect();
        $ignores = [];

        foreach ($doctorants as $doctorant) {
            if ($type && $doctorant->frais()->where('type_frais_id', $type->id)->where('annee_universitaire_id', $annee?->id)->whereNull('annule_le')->exists()) {
                $ignores[] = ['doctorant' => $doctorant->nomComplet(), 'message' => "« {$type->libelle} » déjà appliqué".($annee ? " pour {$annee->libelle}" : '').'.'];

                continue;
            }

            $inscription = $annee
                ? $doctorant->inscriptions()->where('annee_universitaire_id', $annee->id)->where('statut', '!=', StatutInscription::Annulee->value)->first()
                : null;
            $du = $montant ?? $type?->montantPour($inscription) ?? 0;

            if ($du <= 0) {
                $ignores[] = ['doctorant' => $doctorant->nomComplet(), 'message' => 'Montant nul : précisez-le ou définissez le tarif dans les paramètres.'];

                continue;
            }

            $nom = $libelle ?: ($type?->libelle ?? 'Frais');
            $plan = $echeance || ! $type
                ? [['montant' => $du, 'echeance' => $echeance ?? now()->addDays(30)->toDateString(), 'tranche' => null, 'tranches' => null]]
                : $type->planifier($du, now(), $annee);

            foreach ($plan as $tranche) {
                $frais = $doctorant->frais()->create([
                    'type_frais_id' => $type?->id,
                    'annee_universitaire_id' => $annee?->id,
                    'inscription_id' => $inscription?->id,
                    'libelle' => TypeFrais::libelleTranche($nom, $tranche['tranche'], $tranche['tranches']),
                    ...$tranche,
                    'cree_par' => $par->id,
                ]);
                $crees->push($frais);
            }

            $doctorant->user?->notify(new NouveauFrais($frais));
        }

        return ['crees' => $crees, 'ignores' => $ignores];
    }
}
