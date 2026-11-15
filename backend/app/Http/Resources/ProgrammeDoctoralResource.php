<?php

namespace App\Http\Resources;

use App\Models\ProgrammeDoctoral;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProgrammeDoctoral
 */
class ProgrammeDoctoralResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'description' => $this->description,
            'ordre' => $this->ordre,
        ];
    }
}
