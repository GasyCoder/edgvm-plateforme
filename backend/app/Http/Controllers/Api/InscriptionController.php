<?php

namespace App\Http\Controllers\Api;

use App\Enums\StatutInscription;
use App\Enums\TypeInscription;
use App\Http\Controllers\Controller;
use App\Http\Requests\InscriptionRequest;
use App\Http\Resources\InscriptionResource;
use App\Models\Doctorant;
use App\Models\Inscription;
use Illuminate\Http\Response;

class InscriptionController extends Controller
{
    public function store(InscriptionRequest $request, Doctorant $doctorant): InscriptionResource
    {
        $dejaInscrit = $doctorant->inscriptions()->where('statut', '!=', StatutInscription::Annulee->value)->exists();

        $inscription = $doctorant->inscriptions()->create([
            ...$this->donnees($request),
            'type' => $dejaInscrit ? TypeInscription::Reinscription : TypeInscription::Premiere,
            'date_inscription' => $request->input('date_inscription', now()->toDateString()),
        ]);

        return new InscriptionResource($inscription->load('anneeUniversitaire'));
    }

    public function update(InscriptionRequest $request, Inscription $inscription): InscriptionResource
    {
        $inscription->update($this->donnees($request));

        return new InscriptionResource($inscription->load('anneeUniversitaire'));
    }

    /**
     * Une inscription saisie par erreur peut être supprimée ; une inscription réelle s'annule (statut).
     */
    public function destroy(Inscription $inscription): Response
    {
        $this->authorize('delete', $inscription);

        $inscription->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function donnees(InscriptionRequest $request): array
    {
        $donnees = $request->safe()->except('date_inscription');

        if (! $request->boolean('est_derogation')) {
            $donnees = [...$donnees, 'est_derogation' => false, 'motif_derogation' => null, 'decision_derogation' => null, 'date_decision_derogation' => null];
        }

        return $donnees;
    }
}
