<?php

namespace App\Http\Resources;

use App\Models\Doctorant;
use App\Support\SituationFinanciere;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ligne de la situation des paiements : doctorant et totaux.
 *
 * @mixin Doctorant
 */
class SituationDoctorantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'nom_complet' => $this->nomComplet(),
            'matricule' => $this->matricule,
            'equipe' => $this->equipeAccueil?->sigle,
            'niveau' => $this->inscriptionCourante?->niveauAffiche(),
            'prochaine_echeance' => $this->getAttribute('prochaine_echeance') ? substr((string) $this->getAttribute('prochaine_echeance'), 0, 10) : null,
            ...SituationFinanciere::pour($this->resource),
        ];
    }
}
