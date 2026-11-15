<?php

namespace App\Http\Resources;

use App\Models\ModeleActe;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ModeleActe
 */
class ModeleActeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'nom' => $this->nom,
            'titre' => $this->titre,
            'corps' => $this->corps,
            'champs' => $this->champs ?? [],
            'exige_inscription' => $this->exige_inscription,
            'inclure_signature' => $this->inclure_signature,
            'actif' => $this->actif,
            'actes_count' => $this->whenCounted('actes'),
            'modifie_le' => $this->updated_at?->toIso8601String(),
        ];
    }
}
