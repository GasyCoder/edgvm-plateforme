<?php

namespace App\Http\Controllers\Api;

use App\Enums\StatutDoctorant;
use App\Enums\StatutInscription;
use App\Http\Controllers\Controller;
use App\Http\Resources\AnneeUniversitaireResource;
use App\Models\AnneeUniversitaire;
use App\Models\Doctorant;
use App\Models\EquipeAccueil;
use App\Models\Inscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Chiffres de l'année en cours, limités aux équipes d'un membre d'équipe.
 */
class TableauDeBordController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->estDoctorant()) {
            return response()->json(['data' => ['doctorant_id' => $user->doctorant?->uuid]]);
        }

        $annee = AnneeUniversitaire::courante();
        $doctorants = fn (): Builder => Doctorant::query()->visiblesPour($user);
        $inscriptions = fn (): Builder => Inscription::query()
            ->where('annee_universitaire_id', $annee?->id)
            ->where('statut', '!=', StatutInscription::Annulee->value)
            ->whereHas('doctorant', fn (Builder $doctorant) => $doctorant->visiblesPour($user));

        $parNiveau = $inscriptions()->where('est_derogation', false)->selectRaw('niveau, count(*) as total')->groupBy('niveau')->pluck('total', 'niveau');

        return response()->json(['data' => [
            'annee' => $annee ? new AnneeUniversitaireResource($annee) : null,
            'doctorants_actifs' => $doctorants()->where('statut', StatutDoctorant::Actif->value)->count(),
            'inscrits' => $inscriptions()->count(),
            'par_niveau' => [
                'D1' => (int) ($parNiveau['D1'] ?? 0),
                'D2' => (int) ($parNiveau['D2'] ?? 0),
                'D3' => (int) ($parNiveau['D3'] ?? 0),
                'derogation' => $inscriptions()->where('est_derogation', true)->count(),
            ],
            'derogations_sans_decision' => $inscriptions()->where('est_derogation', true)->whereNull('decision_derogation')->count(),
            'actifs_sans_inscription' => $annee ? $doctorants()->where('statut', StatutDoctorant::Actif->value)->whereDoesntHave('inscriptionCourante')->count() : 0,
            'par_equipe' => EquipeAccueil::query()
                ->when(! $user->estSuperAdmin(), fn (Builder $equipe) => $equipe->whereIn('id', $user->idsEquipesSuivies()))
                ->withCount(['doctorants' => fn (Builder $doctorant) => $doctorant->where('statut', StatutDoctorant::Actif->value)])
                ->ordonnees()
                ->get(['id', 'sigle', 'nom'])
                ->map(fn (EquipeAccueil $equipe) => ['id' => $equipe->id, 'sigle' => $equipe->sigle, 'nom' => $equipe->nom, 'doctorants' => $equipe->doctorants_count]),
        ]]);
    }
}
