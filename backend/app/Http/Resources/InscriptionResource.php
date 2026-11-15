<?php

namespace App\Http\Resources;

use App\Models\Inscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Inscription
 */
class InscriptionResource extends JsonResource
{
    /**
     * Rang de la dérogation (« Dérogation 2 »), fourni par la fiche du doctorant.
     */
    public ?int $rangDerogation = null;

    /**
     * Montants et motif visibles : l'administration toujours, le doctorant pour son propre dossier.
     */
    public bool $detailsVisibles = false;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $voitMontants = $request->user()->estSuperAdmin() || $this->detailsVisibles;

        return [
            'id' => $this->id,
            'annee' => new AnneeUniversitaireResource($this->whenLoaded('anneeUniversitaire')),
            'niveau' => $this->niveau->value,
            'niveau_aff' => $this->niveauAffiche($this->rangDerogation),
            'type' => $this->type->value,
            'type_libelle' => $this->type->libelle(),
            'est_derogation' => $this->est_derogation,
            'derogation_en_attente' => $this->derogationEnAttenteDeDecision(),
            'motif_derogation' => $this->when($voitMontants, $this->motif_derogation),
            'decision_derogation' => $this->decision_derogation,
            'date_decision_derogation' => $this->date_decision_derogation?->toDateString(),
            'statut' => $this->statut->value,
            'statut_libelle' => $this->statut->libelle(),
            'frais' => $this->when($voitMontants && $this->relationLoaded('frais'), fn () => $this->frais->whereNull('annule_le')->sum('montant')),
            'date_inscription' => $this->date_inscription?->toDateString(),
        ];
    }
}
