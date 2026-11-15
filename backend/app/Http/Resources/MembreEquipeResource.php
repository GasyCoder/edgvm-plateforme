<?php

namespace App\Http\Resources;

use App\Models\MembreEquipe;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MembreEquipe
 */
class MembreEquipeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'titre' => $this->titre,
            'nom_avec_titre' => $this->nomAvecTitre(),
            'fonction' => $this->fonction->value,
            'fonction_libelle' => $this->fonction->libelle(),
            'specialite' => $this->specialite,
            'etablissement' => $this->etablissement,
            'email' => $this->email,
            'telephone' => $this->telephone,
        ];
    }
}
