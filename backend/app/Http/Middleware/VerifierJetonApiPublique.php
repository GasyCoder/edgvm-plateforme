<?php

namespace App\Http\Middleware;

use App\Support\JetonApiPublique;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * L'API publique ne répond qu'avec le jeton créé dans les paramètres de la plateforme.
 */
class VerifierJetonApiPublique
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! JetonApiPublique::verifier($request->bearerToken())) {
            return response()->json(['message' => JetonApiPublique::estActif()
                ? 'Jeton absent ou invalide.'
                : "L'API publique n'est pas activée : créez un jeton dans les paramètres de la plateforme."], 401);
        }

        return $next($request);
    }
}
