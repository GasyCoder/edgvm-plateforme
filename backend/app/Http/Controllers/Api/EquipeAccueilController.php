<?php

namespace App\Http\Controllers\Api;

use App\Enums\StatutDoctorant;
use App\Http\Controllers\Controller;
use App\Http\Requests\EquipeAccueilRequest;
use App\Http\Resources\EquipeAccueilResource;
use App\Models\EquipeAccueil;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class EquipeAccueilController extends Controller
{
    /**
     * L'administration voit toutes les équipes ; un membre d'équipe voit les siennes.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', EquipeAccueil::class);
        $user = $request->user();

        $equipes = EquipeAccueil::query()
            ->when(! $user->estSuperAdmin(), fn ($requete) => $requete->whereIn('id', $user->idsEquipesSuivies()))
            ->withCount(['doctorants', 'programmes', 'personnes'])
            ->with(['membres', 'responsable', 'programmes'])
            ->ordonnees()
            ->get();

        return EquipeAccueilResource::collection($equipes);
    }

    /**
     * Fiche complète : programmes, personnes, comptes d'accès et doctorants par niveau de l'année.
     */
    public function show(Request $request, EquipeAccueil $equipe): EquipeAccueilResource
    {
        $this->authorize('view', $equipe);

        $niveau = fn (string $niveau) => fn (Builder $doctorant) => $doctorant->where('statut', StatutDoctorant::Actif->value)->niveauCourant($niveau);
        $equipe->loadCount([
            'doctorants',
            'doctorants as effectif_d1' => $niveau('D1'),
            'doctorants as effectif_d2' => $niveau('D2'),
            'doctorants as effectif_d3' => $niveau('D3'),
            'doctorants as effectif_derogation' => $niveau('derogation'),
        ])->load(['programmes', 'personnes', 'membres']);

        return new EquipeAccueilResource($equipe);
    }

    public function store(EquipeAccueilRequest $request): JsonResponse
    {
        $equipe = EquipeAccueil::query()->create($request->validated());

        return (new EquipeAccueilResource($equipe->loadCount(['doctorants', 'programmes', 'personnes'])->load(['membres', 'responsable', 'programmes'])))->response()->setStatusCode(201);
    }

    public function update(EquipeAccueilRequest $request, EquipeAccueil $equipe): EquipeAccueilResource
    {
        $equipe->update($request->validated());

        return new EquipeAccueilResource($equipe->loadCount(['doctorants', 'programmes', 'personnes'])->load(['membres', 'responsable', 'programmes']));
    }

    public function destroy(EquipeAccueil $equipe): Response|JsonResponse
    {
        $this->authorize('delete', $equipe);

        if ($equipe->doctorants()->exists()) {
            return response()->json(['message' => 'Cette équipe accueille des doctorants : changez-les d\'équipe avant de la supprimer.'], 409);
        }

        $equipe->delete();

        return response()->noContent();
    }
}
