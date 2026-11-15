<?php

namespace App\Support;

use App\Enums\SituationPaiement;
use App\Models\Doctorant;
use Illuminate\Database\Eloquent\Builder;

/**
 * Situation de paiement d'un doctorant : dû, payé, reste, retard et statut.
 */
class SituationFinanciere
{
    /**
     * Ajoute aux requêtes de doctorants les totaux nécessaires (sans requête par ligne).
     *
     * @param  Builder<Doctorant>  $query
     * @return Builder<Doctorant>
     */
    public static function avecTotaux(Builder $query): Builder
    {
        return $query
            ->withSum(['frais as total_du' => fn (Builder $f) => $f->whereNull('annule_le')], 'montant')
            ->withSum(['frais as total_paye' => fn (Builder $f) => $f->whereNull('annule_le')], 'montant_paye')
            ->withSum(['frais as total_en_retard' => fn (Builder $f) => $f->enRetard()], 'montant')
            ->withSum(['frais as paye_en_retard' => fn (Builder $f) => $f->enRetard()], 'montant_paye');
    }

    /**
     * @return array{du: int, paye: int, reste: int, en_retard: int, statut: string, statut_libelle: string}
     */
    public static function pour(Doctorant $doctorant): array
    {
        if (! array_key_exists('total_du', $doctorant->getAttributes())) {
            $doctorant = self::avecTotaux(Doctorant::query()->whereKey($doctorant->id))->first() ?? $doctorant;
        }

        $du = (int) $doctorant->getAttribute('total_du');
        $paye = (int) $doctorant->getAttribute('total_paye');
        $enRetard = max(0, (int) $doctorant->getAttribute('total_en_retard') - (int) $doctorant->getAttribute('paye_en_retard'));
        $reste = max(0, $du - $paye);

        $statut = match (true) {
            $du === 0 => SituationPaiement::RienAPayer,
            $reste === 0 => SituationPaiement::Solde,
            $enRetard > 0 => SituationPaiement::EnRetard,
            $paye > 0 => SituationPaiement::Partiel,
            default => SituationPaiement::AVenir,
        };

        return ['du' => $du, 'paye' => $paye, 'reste' => $reste, 'en_retard' => $enRetard, 'statut' => $statut->value, 'statut_libelle' => $statut->libelle()];
    }

    /**
     * « 1500000 » devient « un million cinq cent mille ariary » (si l'extension intl est disponible).
     */
    public static function enLettres(int $montant): ?string
    {
        if (! class_exists(\NumberFormatter::class)) {
            return null;
        }

        $texte = (new \NumberFormatter('fr', \NumberFormatter::SPELLOUT))->format($montant);

        return $texte ? $texte.' ariary' : null;
    }
}
