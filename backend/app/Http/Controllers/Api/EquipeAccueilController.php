<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EquipeAccueilRequest;
use App\Http\Resources\EquipeAccueilResource;
use App\Models\EquipeAccueil;
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
            ->withCount('doctorants')
            ->with('membres')
            ->ordonnees()
            ->get();

        return EquipeAccueilResource::collection($equipes);
    }

    public function store(EquipeAccueilRequest $request): JsonResponse
    {
        $equipe = EquipeAccueil::query()->create($request->validated());

        return (new EquipeAccueilResource($equipe->loadCount('doctorants')->load('membres')))->response()->setStatusCode(201);
    }

    public function update(EquipeAccueilRequest $request, EquipeAccueil $equipe): EquipeAccueilResource
    {
        $equipe->update($request->validated());

        return new EquipeAccueilResource($equipe->loadCount('doctorants')->load('membres'));
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
