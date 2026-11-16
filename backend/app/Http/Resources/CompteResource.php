<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Support\PhotoUtilisateur;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class CompteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'telephone' => $this->telephone,
            'photo_url' => PhotoUtilisateur::url($this->resource),
            'role' => $this->role->value,
            'role_libelle' => $this->role->libelle(),
            'actif' => $this->actif,
            'fonction' => $this->whenPivotLoaded('equipe_accueil_user', fn () => $this->pivot->fonction),
            'derniere_connexion_le' => $this->derniere_connexion_le?->toIso8601String(),
            'equipes' => EquipeAccueilResource::collection($this->whenLoaded('equipesAccueil')),
            'doctorant_id' => $this->whenLoaded('doctorant', fn () => $this->doctorant?->uuid),
        ];
    }
}
