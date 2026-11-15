<?php

namespace App\Http\Resources;

use App\Enums\NiveauDoctorat;
use App\Enums\StatutInscription;
use App\Models\Doctorant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Dossier doctorant. Les données personnelles ne sont visibles que par l'administration et le doctorant lui-même.
 *
 * @mixin Doctorant
 */
class DoctorantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $personnel = $user->can('viewDonneesPersonnelles', $this->resource);
        $rangs = $this->relationLoaded('inscriptions') ? $this->rangsDerogation() : [];

        return [
            'id' => $this->uuid,
            'matricule' => $this->matricule,
            'nom' => $this->nom,
            'prenoms' => $this->prenoms,
            'nom_complet' => $this->nomComplet(),
            'genre' => $this->genre,
            'email' => $this->whenLoaded('user', fn () => $this->user->email),
            'telephone' => $this->whenLoaded('user', fn () => $this->user->telephone),
            'date_naissance' => $this->when($personnel, fn () => $this->date_naissance?->toDateString()),
            'lieu_naissance' => $this->when($personnel, $this->lieu_naissance),
            'nationalite' => $this->when($personnel, $this->nationalite),
            'numero_piece' => $this->when($personnel, $this->numero_piece),
            'adresse' => $this->when($personnel, $this->adresse),
            'observation' => $this->when($user->estSuperAdmin(), $this->observation),
            'equipe' => new EquipeAccueilResource($this->whenLoaded('equipeAccueil')),
            'sujet_these' => $this->sujet_these,
            'directeur_these' => $this->directeur_these,
            'co_directeur' => $this->co_directeur,
            'co_encadrant' => $this->co_encadrant,
            'statut' => $this->statut->value,
            'statut_libelle' => $this->statut->libelle(),
            'visible_annuaire' => $this->visible_annuaire,
            'compte' => $this->when($user->estSuperAdmin() && $this->relationLoaded('user'), fn () => [
                'id' => $this->user_id,
                'actif' => $this->user->actif,
                'derniere_connexion_le' => $this->user->derniere_connexion_le?->toIso8601String(),
            ]),
            'inscription_courante' => $this->whenLoaded('inscriptionCourante', fn () => $this->inscriptionCourante
                ? $this->avecRang(new InscriptionResource($this->inscriptionCourante), $rangs, $personnel)
                : null),
            'inscriptions' => $this->whenLoaded('inscriptions', fn () => $this->inscriptions
                ->sortByDesc(fn ($inscription) => $inscription->anneeUniversitaire?->debut)
                ->values()
                ->map(fn ($inscription) => $this->avecRang(new InscriptionResource($inscription), $rangs, $personnel))),
            'prochaine_inscription' => $this->when(
                $user->estSuperAdmin() && $this->relationLoaded('inscriptions'),
                fn () => $this->prochaineInscription(),
            ),
            'peut' => [
                'modifier' => $user->can('update', $this->resource),
            ],
        ];
    }

    /**
     * @param  array<int, int>  $rangs
     */
    private function avecRang(InscriptionResource $ressource, array $rangs, bool $detailsVisibles): InscriptionResource
    {
        $ressource->rangDerogation = $rangs[$ressource->resource->id] ?? null;
        $ressource->detailsVisibles = $detailsVisibles;

        return $ressource;
    }

    /**
     * Niveau proposé pour la prochaine inscription : l'année suivante, ou une dérogation après D3.
     *
     * @return array{niveau: string, est_derogation: bool, type: string}
     */
    private function prochaineInscription(): array
    {
        $nombre = $this->inscriptions->where('statut', '!=', StatutInscription::Annulee)->count();
        $derogation = $nombre >= NiveauDoctorat::DUREE_NORMALE;

        return [
            'niveau' => $derogation ? NiveauDoctorat::D3->value : 'D'.($nombre + 1),
            'est_derogation' => $derogation,
            'type' => $nombre === 0 ? 'premiere' : 'reinscription',
        ];
    }
}
