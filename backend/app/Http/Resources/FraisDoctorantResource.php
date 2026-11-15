<?php

namespace App\Http\Resources;

use App\Models\FraisDoctorant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FraisDoctorant
 */
class FraisDoctorantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $situation = $this->situation();

        return [
            'id' => $this->id,
            'libelle' => $this->libelle,
            'annee' => $this->whenLoaded('anneeUniversitaire', fn () => $this->anneeUniversitaire?->libelle),
            'montant' => $this->montant,
            'paye' => $this->montant_paye,
            'reste' => $this->reste(),
            'echeance' => $this->echeance?->toDateString(),
            'tranche' => $this->tranche,
            'tranches' => $this->tranches,
            'situation' => $situation->value,
            'situation_libelle' => $situation->libelle(),
            'motif_ajustement' => $this->motif_ajustement,
            'annule_le' => $this->annule_le?->toIso8601String(),
            'motif_annulation' => $this->motif_annulation,
        ];
    }
}
