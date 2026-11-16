<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\LienTelechargement;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un lien signé (application mobile) vaut connexion pour cette seule requête, sans ouvrir de session.
 */
class AutoriserLienTelechargement
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->has('_sig') && ! Auth::guard('web')->check()) {
            $id = LienTelechargement::utilisateur($request);
            $user = $id ? User::query()->find($id) : null;
            abort_if($user === null || ! $user->actif, 403, 'Ce lien a expiré. Rouvrez le document depuis l\'application.');
            Auth::guard('web')->setUser($user);
        }

        return $next($request);
    }
}
