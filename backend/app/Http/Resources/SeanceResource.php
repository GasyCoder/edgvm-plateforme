<?php

namespace App\Http\Resources;

use App\Models\Passage;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Seance
 */
class SeanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $equipes = $user->estMembreEad() ? array_map('intval', $user->idsEquipesSuivies()) : [];

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_libelle' => $this->type->libelle(),
            'titre' => $this->titre,
            'description' => $this->description,
            'lieu' => $this->lieu,
            'date' => $this->date->toDateString(),
            'heure_debut' => substr((string) $this->heure_debut, 0, 5),
            'heure_fin' => $this->when($this->relationLoaded('passages'), fn () => $this->passages->max('fin')?->format('H:i')),
            'duree_passage' => $this->duree_passage,
            'pause' => $this->pause,
            'annee_universitaire_id' => $this->annee_universitaire_id,
            'exige_paiement_solde' => $this->exige_paiement_solde,
            'statut' => $this->statut->value,
            'statut_libelle' => $this->statut->libelle(),
            'convocations_envoyees_le' => $this->convocations_envoyees_le?->toIso8601String(),
            'passages_count' => $this->whenCounted('passages'),
            'passages' => $this->whenLoaded('passages', fn () => PassageResource::collection($this->passages->filter(fn (Passage $passage) => $this->passageVisible($passage, $user, $equipes))->values())),
            'peut' => ['modifier' => $user->can('update', $this->resource)],
        ];
    }

    /** Un doctorant ne voit que son passage ; un membre d'équipe, ceux de ses doctorants et ceux où il intervient. */
    /**
     * @param  array<int, int>  $equipes
     */
    private function passageVisible(Passage $passage, User $user, array $equipes): bool
    {
        return match (true) {
            $user->estSuperAdmin() => true,
            $user->estDoctorant() => $passage->doctorant_id === $user->doctorant?->id,
            default => in_array($passage->doctorant->equipe_accueil_id, $equipes, true)
                || $passage->intervenants->contains(fn ($membre) => $membre->email && $membre->email === $user->email),
        };
    }
}
