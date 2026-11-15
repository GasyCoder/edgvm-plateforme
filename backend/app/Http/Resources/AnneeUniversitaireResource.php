<?php

namespace App\Http\Resources;

use App\Models\AnneeUniversitaire;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AnneeUniversitaire
 */
class AnneeUniversitaireResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'libelle' => $this->libelle,
            'debut' => $this->debut->toDateString(),
            'fin' => $this->fin->toDateString(),
            'est_courante' => $this->est_courante,
            'inscriptions_count' => $this->whenCounted('inscriptions'),
        ];
    }
}
