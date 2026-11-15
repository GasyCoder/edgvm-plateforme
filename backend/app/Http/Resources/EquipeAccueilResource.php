<?php

namespace App\Http\Resources;

use App\Models\EquipeAccueil;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EquipeAccueil
 */
class EquipeAccueilResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sigle' => $this->sigle,
            'nom' => $this->nom,
            'description' => $this->description,
            'fonction' => $this->whenPivotLoaded('equipe_accueil_user', fn () => $this->pivot->fonction),
            'doctorants_count' => $this->whenCounted('doctorants'),
            'membres' => CompteResource::collection($this->whenLoaded('membres')),
        ];
    }
}
