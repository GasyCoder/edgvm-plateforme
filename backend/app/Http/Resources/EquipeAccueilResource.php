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
            'domaine' => $this->domaine,
            'description' => $this->description,
            'fonction' => $this->whenPivotLoaded('equipe_accueil_user', fn () => $this->pivot->fonction),
            'doctorants_count' => $this->whenCounted('doctorants'),
            'programmes_count' => $this->whenCounted('programmes'),
            'personnes_count' => $this->whenCounted('personnes'),
            'responsable' => $this->whenLoaded('responsable', fn () => $this->responsable ? new MembreEquipeResource($this->responsable) : null),
            'programmes' => ProgrammeDoctoralResource::collection($this->whenLoaded('programmes')),
            'personnes' => MembreEquipeResource::collection($this->whenLoaded('personnes')),
            'comptes' => CompteResource::collection($this->whenLoaded('membres')),
            'effectifs' => $this->when(array_key_exists('effectif_d1', $this->resource->getAttributes()), fn () => [
                'D1' => (int) $this->resource->getAttribute('effectif_d1'),
                'D2' => (int) $this->resource->getAttribute('effectif_d2'),
                'D3' => (int) $this->resource->getAttribute('effectif_d3'),
                'derogation' => (int) $this->resource->getAttribute('effectif_derogation'),
            ]),
        ];
    }
}
