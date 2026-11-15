<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Sert l'interface Nuxt (construite en fichiers statiques dans public/) pour toutes les adresses
 * qui ne sont ni l'API ni un fichier : c'est elle qui affiche la bonne page.
 */
class InterfaceController extends Controller
{
    public const FICHIER = 'app.html';

    public function __invoke(Request $request): Response|JsonResponse
    {
        if ($request->is('api/*', 'sanctum/*', 'telechargements/*', 'verifier/*')) {
            return response()->json(['message' => 'Adresse introuvable.'], 404);
        }

        $chemin = public_path(self::FICHIER);

        if (! is_file($chemin)) {
            return response("L'interface n'est pas encore installée.", 503);
        }

        return response(file_get_contents($chemin), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-cache, private',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
