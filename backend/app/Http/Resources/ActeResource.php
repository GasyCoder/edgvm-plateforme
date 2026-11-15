<?php

namespace App\Http\Resources;

use App\Models\Acte;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Acte
 */
class ActeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'titre' => $this->titre,
            'modele' => $this->whenLoaded('modele', fn () => ['id' => $this->modele->id, 'nom' => $this->modele->nom]),
            'doctorant' => $this->whenLoaded('doctorant', fn () => [
                'id' => $this->doctorant->uuid,
                'nom_complet' => $this->doctorant->nomComplet(),
                'matricule' => $this->doctorant->matricule,
            ]),
            'annee' => $this->whenLoaded('anneeUniversitaire', fn () => $this->anneeUniversitaire?->libelle),
            'delivre_le' => $this->delivre_le->toIso8601String(),
            'delivre_par' => $this->whenLoaded('delivrePar', fn () => $this->delivrePar?->name),
            'annule_le' => $this->annule_le?->toIso8601String(),
            'motif_annulation' => $this->motif_annulation,
            'url_pdf' => '/telechargements/actes/'.$this->id.'.pdf',
            'peut_annuler' => $request->user()->can('annuler', $this->resource),
        ];
    }
}
