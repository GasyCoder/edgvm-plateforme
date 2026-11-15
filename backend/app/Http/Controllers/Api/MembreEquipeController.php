<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MembreEquipeRequest;
use App\Http\Resources\MembreEquipeResource;
use App\Models\EquipeAccueil;
use App\Models\MembreEquipe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Personnes d'une équipe d'accueil : responsable, encadrants et membres (administration).
 */
class MembreEquipeController extends Controller
{
    public function store(MembreEquipeRequest $request, EquipeAccueil $equipe): JsonResponse
    {
        $membre = $equipe->personnes()->create($request->validated());

        return (new MembreEquipeResource($membre))->response()->setStatusCode(201);
    }

    public function update(MembreEquipeRequest $request, MembreEquipe $membre): MembreEquipeResource
    {
        $membre->update($request->validated());

        return new MembreEquipeResource($membre);
    }

    public function destroy(MembreEquipe $membre): Response
    {
        $this->authorize('update', $membre->equipeAccueil);

        $membre->delete();

        return response()->noContent();
    }
}
