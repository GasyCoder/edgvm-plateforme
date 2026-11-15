<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnneeUniversitaireRequest;
use App\Http\Resources\AnneeUniversitaireResource;
use App\Models\AnneeUniversitaire;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AnneeUniversitaireController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return AnneeUniversitaireResource::collection(
            AnneeUniversitaire::query()->withCount('inscriptions')->orderByDesc('debut')->get()
        );
    }

    public function store(AnneeUniversitaireRequest $request): JsonResponse
    {
        $annee = AnneeUniversitaire::query()->create([...$request->safe()->except('est_courante'), 'est_courante' => false]);

        if ($request->boolean('est_courante')) {
            $annee->definirCommeCourante();
        }

        return (new AnneeUniversitaireResource($annee->refresh()->loadCount('inscriptions')))->response()->setStatusCode(201);
    }

    public function update(AnneeUniversitaireRequest $request, AnneeUniversitaire $annee): AnneeUniversitaireResource
    {
        $annee->update($request->safe()->except('est_courante'));

        if ($request->boolean('est_courante')) {
            $annee->definirCommeCourante();
        }

        return new AnneeUniversitaireResource($annee->refresh()->loadCount('inscriptions'));
    }
}
