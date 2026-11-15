<?php

namespace App\Http\Controllers\Api;

use App\Actions\AppliquerFraisInscription;
use App\Enums\StatutInscription;
use App\Enums\TypeInscription;
use App\Http\Controllers\Controller;
use App\Http\Requests\InscriptionRequest;
use App\Http\Resources\InscriptionResource;
use App\Models\Doctorant;
use App\Models\Inscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class InscriptionController extends Controller
{
    public function store(InscriptionRequest $request, Doctorant $doctorant, AppliquerFraisInscription $appliquerFrais): InscriptionResource
    {
        $dejaInscrit = $doctorant->inscriptions()->where('statut', '!=', StatutInscription::Annulee->value)->exists();

        $inscription = $doctorant->inscriptions()->create([
            ...$this->donnees($request),
            'type' => $dejaInscrit ? TypeInscription::Reinscription : TypeInscription::Premiere,
            'date_inscription' => $request->input('date_inscription', now()->toDateString()),
        ]);

        if ($inscription->statut !== StatutInscription::Annulee) {
            $appliquerFrais($inscription, $request->user());
        }

        return new InscriptionResource($inscription->load(['anneeUniversitaire', 'frais']));
    }

    /**
     * Une inscription annulée annule aussi ses frais encore non réglés.
     */
    public function update(InscriptionRequest $request, Inscription $inscription): InscriptionResource
    {
        $inscription->update($this->donnees($request));

        if ($inscription->statut === StatutInscription::Annulee) {
            $inscription->frais()->whereNull('annule_le')->where('montant_paye', 0)
                ->update(['annule_le' => now(), 'motif_annulation' => 'Inscription annulée']);
        }

        return new InscriptionResource($inscription->load(['anneeUniversitaire', 'frais']));
    }

    /**
     * Une inscription saisie par erreur peut être supprimée ; une inscription réelle s'annule (statut).
     */
    public function destroy(Inscription $inscription): Response|JsonResponse
    {
        $this->authorize('delete', $inscription);

        if ($inscription->frais()->where('montant_paye', '>', 0)->exists()) {
            return response()->json(['message' => 'Des paiements sont liés à cette inscription : annulez-la plutôt que de la supprimer.'], 409);
        }

        DB::transaction(function () use ($inscription): void {
            $inscription->frais()->delete();
            $inscription->delete();
        });

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
