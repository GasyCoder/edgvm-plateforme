<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Frais paramétrable : montant unique ou par niveau, appliqué ou non automatiquement à chaque inscription.
 *
 * @property array{D1?: int, D2?: int, D3?: int, derogation?: int}|null $montants_niveaux
 */
#[Table('types_frais')]
#[Fillable(['code', 'libelle', 'description', 'montant', 'montants_niveaux', 'obligatoire', 'echeance', 'delai_jours', 'tranches', 'actif', 'ordre'])]
class TypeFrais extends Model
{
    use HasFactory;

    public const NIVEAUX = ['D1', 'D2', 'D3', 'derogation'];

    /** Échéance : fin de l'année universitaire du frais, ou délai en jours après sa date */
    public const ECHEANCE_FIN_ANNEE = 'fin_annee';

    public const ECHEANCE_DELAI = 'delai';

    public const TRANCHES_MAX = 4;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'montant' => 'integer',
            'montants_niveaux' => 'array',
            'obligatoire' => 'boolean',
            'delai_jours' => 'integer',
            'tranches' => 'integer',
            'actif' => 'boolean',
        ];
    }

    /**
     * @return HasMany<FraisDoctorant, $this>
     */
    public function frais(): HasMany
    {
        return $this->hasMany(FraisDoctorant::class);
    }

    public function estParNiveau(): bool
    {
        return ! empty($this->montants_niveaux);
    }

    /**
     * Découpe un montant en tranches datées. Fin d'année : dates réparties jusqu'à la fin de l'année universitaire
     * (la dernière tombe à la fin). Délai : une tranche tous les « delai_jours ». Les montants sont arrondis au millier,
     * la dernière tranche prend le reste.
     *
     * @return array<int, array{montant: int, echeance: string, tranche: ?int, tranches: ?int}>
     */
    public function planifier(int $montant, CarbonInterface $debut, ?AnneeUniversitaire $annee): array
    {
        $nombre = max(1, min(self::TRANCHES_MAX, $this->tranches ?: 1));
        $debut = CarbonImmutable::parse($debut)->startOfDay();
        $fin = $this->echeance === self::ECHEANCE_FIN_ANNEE && $annee && $annee->fin->gt($debut)
            ? CarbonImmutable::parse($annee->fin)->startOfDay()
            : null;

        $part = $nombre > 1 ? (int) (floor($montant / $nombre / 1000) * 1000) : $montant;
        $plan = [];

        for ($rang = 1; $rang <= $nombre; $rang++) {
            $echeance = $fin
                ? ($rang === $nombre ? $fin : $debut->addDays((int) round($debut->diffInDays($fin) * $rang / $nombre)))
                : $debut->addDays($this->delai_jours * $rang);

            $plan[] = [
                'montant' => $rang === $nombre ? $montant - $part * ($nombre - 1) : $part,
                'echeance' => $echeance->toDateString(),
                'tranche' => $nombre > 1 ? $rang : null,
                'tranches' => $nombre > 1 ? $nombre : null,
            ];
        }

        return $plan;
    }

    public static function libelleTranche(string $libelle, ?int $tranche, ?int $tranches): string
    {
        return $tranche && $tranches ? "{$libelle}, tranche {$tranche}/{$tranches}" : $libelle;
    }

    /**
     * Montant pour une inscription donnée (tarif du niveau, ou de la dérogation), sinon le montant unique.
     */
    public function montantPour(?Inscription $inscription): int
    {
        if ($inscription && $this->estParNiveau()) {
            $cle = $inscription->est_derogation ? 'derogation' : $inscription->niveau->value;

            return (int) ($this->montants_niveaux[$cle] ?? $this->montant);
        }

        return $this->montant;
    }
}
