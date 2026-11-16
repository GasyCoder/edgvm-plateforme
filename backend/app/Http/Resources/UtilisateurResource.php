<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Support\PhotoUtilisateur;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Utilisateur connecté (« /api/moi ») : de quoi construire les menus de l'interface.
 *
 * @mixin User
 */
class UtilisateurResource extends JsonResource
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
            'doctorant_id' => $this->whenLoaded('doctorant', fn () => $this->doctorant?->uuid),
            'equipes' => EquipeAccueilResource::collection($this->whenLoaded('equipesAccueil')),
        ];
    }
}
