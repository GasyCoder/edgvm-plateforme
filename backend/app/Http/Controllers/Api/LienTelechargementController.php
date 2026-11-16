<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\LienTelechargement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Application mobile : transforme un lien « /telechargements/... » en lien signé à ouvrir dans le navigateur du téléphone.
 * Les droits restent vérifiés au moment du téléchargement, comme avec la session.
 */
class LienTelechargementController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $donnees = $request->validate(
            ['chemin' => ['required', 'string', 'max:2000', 'regex:#^/telechargements/[^\s]+$#']],
            ['chemin.regex' => 'Seuls les documents de la plateforme peuvent être ouverts.'],
        );

        return response()->json(['data' => [
            'url' => LienTelechargement::signer($donnees['chemin'], $request->user()),
            'expire_dans' => LienTelechargement::DUREE_SECONDES,
        ]]);
    }
}
