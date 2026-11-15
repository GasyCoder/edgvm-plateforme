<?php

namespace App\Http\Resources;

use App\Models\PieceJointe;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PieceJointe
 */
class PieceJointeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'categorie' => $this->categorie->value,
            'categorie_libelle' => $this->categorie->libelle(),
            'titre' => $this->titre,
            'nom_original' => $this->nom_original,
            'type_mime' => $this->type_mime,
            'taille' => $this->taille,
            'affichable' => $this->peutSAfficher(),
            'ajoute_par' => $this->whenLoaded('auteur', fn () => $this->auteur?->name),
            'ajoute_le' => $this->created_at?->toIso8601String(),
            'peut_supprimer' => $request->user()->can('delete', $this->resource),
        ];
    }
}
