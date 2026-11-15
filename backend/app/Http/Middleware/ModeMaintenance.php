<?php

namespace App\Http\Middleware;

use App\Support\ReglagesApplication;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Maintenance de la plateforme : seule l'administration de l'École continue à travailler.
 * Les autres comptes reçoivent le message de maintenance (l'interface l'affiche en plein écran).
 */
class ModeMaintenance
{
    /** Routes toujours permises : savoir qui est connecté, se déconnecter */
    private const PERMISES = ['moi', 'deconnexion'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->estSuperAdmin() && ! $request->routeIs(...self::PERMISES) && ReglagesApplication::maintenanceActive()) {
            return response()->json([
                'message' => ReglagesApplication::texte('maintenance.message'),
                'maintenance' => true,
            ], 503);
        }

        return $next($request);
    }
}
