<?php

namespace App\Http\Resources;

use App\Enums\RoleIntervenant;
use App\Models\MembreEquipe;
use App\Models\Passage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Heures sans fuseau (heure locale de l'École), lues telles quelles par l'interface.
 *
 * @mixin Passage
 */
class PassageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $admin = $request->user()->estSuperAdmin();
        $inscription = $this->doctorant->inscriptionCourante;

        return [
            'id' => $this->id,
            'debut' => $this->debut->format('Y-m-d\TH:i'),
            'fin' => $this->fin->format('Y-m-d\TH:i'),
            'heure_debut' => $this->debut->format('H:i'),
            'heure_fin' => $this->fin->format('H:i'),
            'duree' => (int) $this->debut->diffInMinutes($this->fin),
            'sujet' => $this->sujet ?? $this->doctorant->sujet_these,
            'ordre' => $this->ordre,
            'convoque_le' => $this->convoque_le?->toIso8601String(),
            'convocation' => $this->etatConvocation(),
            'decision' => $this->decision?->value,
            'decision_libelle' => $this->decision?->libelle(),
            'decision_observation' => $admin ? $this->decision_observation : null,
            'decision_notifiee_le' => $this->decision_notifiee_le?->toIso8601String(),
            'doctorant' => [
                'id' => $this->doctorant->uuid,
                'nom_complet' => $this->doctorant->nomComplet(),
                'matricule' => $this->doctorant->matricule,
                'photo_url' => $this->doctorant->photo_chemin ? '/telechargements/doctorants/'.$this->doctorant->uuid.'/photo?v='.substr(md5($this->doctorant->photo_chemin), 0, 8) : null,
                'niveau' => $this->doctorant->estCandidat() ? 'Candidat' : $inscription?->niveauAffiche(),
                'est_derogation' => (bool) $inscription?->est_derogation,
                'equipe' => $this->doctorant->equipeAccueil?->sigle,
                'statut' => $this->doctorant->statut->value,
                'admis_le' => $this->doctorant->admis_le?->toIso8601String(),
            ],
            'intervenants' => $this->intervenants->map(fn (MembreEquipe $membre) => [
                'id' => $membre->id,
                'nom_avec_titre' => $membre->nomAvecTitre(),
                'role' => $membre->pivot->role,
                'role_libelle' => RoleIntervenant::from($membre->pivot->role)->libelle(),
                'equipe' => $membre->relationLoaded('equipeAccueil') ? $membre->equipeAccueil?->sigle : null,
                'email' => $admin ? $membre->email : null,
            ])->values(),
        ];
    }
}
