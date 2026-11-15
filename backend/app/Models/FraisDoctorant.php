<?php

namespace App\Models;

use App\Enums\SituationPaiement;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Montant dû par un doctorant (droits d'inscription, tee-shirt...). Le payé est la somme des paiements non annulés.
 */
#[Table('frais_doctorants')]
#[Fillable([
    'doctorant_id', 'type_frais_id', 'annee_universitaire_id', 'inscription_id', 'libelle', 'montant', 'montant_paye',
    'echeance', 'tranche', 'tranches', 'motif_ajustement', 'annule_le', 'motif_annulation', 'cree_par',
])]
class FraisDoctorant extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'montant' => 'integer',
            'montant_paye' => 'integer',
            'echeance' => 'date',
            'annule_le' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Doctorant, $this>
     */
    public function doctorant(): BelongsTo
    {
        return $this->belongsTo(Doctorant::class);
    }

    /**
     * @return BelongsTo<TypeFrais, $this>
     */
    public function typeFrais(): BelongsTo
    {
        return $this->belongsTo(TypeFrais::class);
    }

    /**
     * @return BelongsTo<AnneeUniversitaire, $this>
     */
    public function anneeUniversitaire(): BelongsTo
    {
        return $this->belongsTo(AnneeUniversitaire::class);
    }

    /**
     * @return BelongsTo<Inscription, $this>
     */
    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class);
    }

    /**
     * @return HasMany<PaiementFrais, $this>
     */
    public function imputations(): HasMany
    {
        return $this->hasMany(PaiementFrais::class);
    }

    public function reste(): int
    {
        return $this->annule_le ? 0 : max(0, $this->montant - $this->montant_paye);
    }

    public function situation(): SituationPaiement
    {
        return match (true) {
            $this->annule_le !== null => SituationPaiement::Annule,
            $this->reste() === 0 => SituationPaiement::Solde,
            $this->echeance !== null && $this->echeance->lt(today()) => SituationPaiement::EnRetard,
            $this->montant_paye > 0 => SituationPaiement::Partiel,
            default => SituationPaiement::AVenir,
        };
    }

    /**
     * Recalcule le payé à partir des paiements non annulés.
     */
    public function recalculerPaye(): void
    {
        $this->update([
            'montant_paye' => (int) $this->imputations()->whereHas('paiement', fn (Builder $p) => $p->whereNull('annule_le'))->sum('montant'),
        ]);
    }

    /**
     * Frais non annulés restant à payer.
     *
     * @param  Builder<FraisDoctorant>  $query
     */
    public function scopeImpayes(Builder $query): void
    {
        $query->whereNull('annule_le')->whereColumn('montant_paye', '<', 'montant');
    }

    /**
     * Frais impayés dont l'échéance est passée.
     *
     * @param  Builder<FraisDoctorant>  $query
     */
    public function scopeEnRetard(Builder $query): void
    {
        $query->impayes()->whereNotNull('echeance')->whereDate('echeance', '<', today());
    }
}
