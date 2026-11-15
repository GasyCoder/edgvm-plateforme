<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\JetonApiPublique;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Accès du site vitrine à l'API publique : état, création et révocation du jeton (administration).
 */
class ApiPubliqueController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        abort_unless($request->user()->estSuperAdmin(), 403);

        return response()->json(['data' => $this->etat()]);
    }

    /**
     * Crée un nouveau jeton (l'ancien cesse aussitôt de fonctionner). Le jeton en clair n'est renvoyé qu'ici.
     */
    public function generer(Request $request): JsonResponse
    {
        abort_unless($request->user()->estSuperAdmin(), 403);

        $jeton = JetonApiPublique::generer();

        return response()->json(['data' => [...$this->etat(), 'jeton' => $jeton], 'message' => 'Nouveau jeton créé. Copiez-le maintenant : il ne sera plus affiché.'], 201);
    }

    public function revoquer(Request $request): JsonResponse
    {
        abort_unless($request->user()->estSuperAdmin(), 403);

        JetonApiPublique::revoquer();

        return response()->json(['data' => $this->etat(), 'message' => "Jeton révoqué : le site ne peut plus lire l'API."]);
    }

    /**
     * @return array{actif: bool, cree_le: ?string, url: string}
     */
    private function etat(): array
    {
        return ['actif' => JetonApiPublique::estActif(), 'cree_le' => JetonApiPublique::creeLe(), 'url' => url('/api/public/v1')];
    }
}
