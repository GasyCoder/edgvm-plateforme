<?php

namespace App\Http\Resources;

use App\Models\Paiement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Paiement
 */
class PaiementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero_recu' => $this->numero_recu,
            'date_paiement' => $this->date_paiement->toDateString(),
            'enregistre_le' => $this->created_at?->toIso8601String(),
            'montant' => $this->montant,
            'reste_apres' => $this->reste_apres,
            'mode' => $this->mode->value,
            'mode_libelle' => $this->mode->libelle(),
            'reference' => $this->reference,
            'observation' => $this->observation,
            'doctorant' => $this->whenLoaded('doctorant', fn () => ['id' => $this->doctorant->uuid, 'nom_complet' => $this->doctorant->nomComplet(), 'matricule' => $this->doctorant->matricule]),
            'imputations' => $this->whenLoaded('imputations', fn () => $this->imputations->map(fn ($imputation) => [
                'libelle' => $imputation->frais?->libelle,
                'montant' => $imputation->montant,
            ])),
            'saisi_par' => $this->whenLoaded('saisiPar', fn () => $this->saisiPar?->name),
            'annule_le' => $this->annule_le?->toIso8601String(),
            'motif_annulation' => $this->motif_annulation,
            'url_recu' => '/telechargements/paiements/'.$this->id.'/recu.pdf',
            'justificatif' => $this->justificatif_chemin ? [
                'nom' => $this->justificatif_nom,
                'type' => $this->justificatif_type,
                'taille' => $this->justificatif_taille,
                'est_image' => str_starts_with((string) $this->justificatif_type, 'image/'),
                'url' => '/telechargements/paiements/'.$this->id.'/justificatif',
            ] : null,
            'peut_annuler' => $request->user()->can('annuler', $this->resource),
        ];
    }
}
