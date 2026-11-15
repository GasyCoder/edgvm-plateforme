<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProgrammeDoctoralRequest;
use App\Http\Resources\ProgrammeDoctoralResource;
use App\Models\EquipeAccueil;
use App\Models\ProgrammeDoctoral;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Programmes doctoraux d'une équipe d'accueil (administration).
 */
class ProgrammeDoctoralController extends Controller
{
    public function store(ProgrammeDoctoralRequest $request, EquipeAccueil $equipe): JsonResponse
    {
        $programme = $equipe->programmes()->create([
            ...$request->validated(),
            'ordre' => $request->validated('ordre') ?? (int) $equipe->programmes()->max('ordre') + 1,
        ]);

        return (new ProgrammeDoctoralResource($programme))->response()->setStatusCode(201);
    }

    public function update(ProgrammeDoctoralRequest $request, ProgrammeDoctoral $programme): ProgrammeDoctoralResource
    {
        $programme->update(array_filter($request->validated(), fn ($valeur, string $cle) => $cle !== 'ordre' || $valeur !== null, ARRAY_FILTER_USE_BOTH));

        return new ProgrammeDoctoralResource($programme);
    }

    public function destroy(ProgrammeDoctoral $programme): Response
    {
        $this->authorize('update', $programme->equipeAccueil);

        $programme->delete();

        return response()->noContent();
    }
}
