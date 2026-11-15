<?php

namespace App\Models;

use App\Enums\NiveauDoctorat;
use App\Enums\StatutInscription;
use App\Enums\TypeInscription;
use Database\Factories\InscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Inscription annuelle d'un doctorant. D1 à D3 en durée normale ; au delà, c'est une dérogation.
 */
#[Fillable([
    'doctorant_id', 'annee_universitaire_id', 'niveau', 'type', 'est_derogation', 'motif_derogation',
    'decision_derogation', 'date_decision_derogation', 'statut', 'montant_du', 'date_inscription',
])]
class Inscription extends Model
{
    /** @use HasFactory<InscriptionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'niveau' => NiveauDoctorat::class,
            'type' => TypeInscription::class,
            'statut' => StatutInscription::class,
            'est_derogation' => 'boolean',
            'date_decision_derogation' => 'date',
            'date_inscription' => 'date',
            'montant_du' => 'integer',
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
     * @return BelongsTo<AnneeUniversitaire, $this>
     */
    public function anneeUniversitaire(): BelongsTo
    {
        return $this->belongsTo(AnneeUniversitaire::class);
    }

    /**
     * Une dérogation n'est complète qu'avec la décision qui l'autorise.
     */
    public function derogationEnAttenteDeDecision(): bool
    {
        return $this->est_derogation && blank($this->decision_derogation);
    }

    /**
     * Libellé du niveau : « D2 », ou « Dérogation 1 » pour la première année au delà de D3.
     */
    public function niveauAffiche(?int $rangDerogation = null): string
    {
        if (! $this->est_derogation) {
            return $this->niveau->value;
        }

        return $rangDerogation ? "Dérogation {$rangDerogation}" : 'Dérogation';
    }
}
