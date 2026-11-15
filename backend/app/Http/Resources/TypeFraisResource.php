<?php

namespace App\Http\Resources;

use App\Models\TypeFrais;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TypeFrais
 */
class TypeFraisResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'libelle' => $this->libelle,
            'description' => $this->description,
            'par_niveau' => $this->estParNiveau(),
            'montant' => $this->montant,
            'montants_niveaux' => $this->montants_niveaux,
            'obligatoire' => $this->obligatoire,
            'echeance' => $this->echeance,
            'delai_jours' => $this->delai_jours,
            'tranches' => $this->tranches,
            'actif' => $this->actif,
            'frais_count' => $this->whenCounted('frais'),
            'frais_non_soldes_count' => $this->whenCounted('frais_non_soldes'),
        ];
    }
}
