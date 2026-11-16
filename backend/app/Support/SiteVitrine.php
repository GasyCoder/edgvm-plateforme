<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Prévient le site vitrine qu'une donnée publiée a changé : il vide son cache et relit l'API aussitôt,
 * au lieu d'attendre jusqu'à 10 minutes.
 *
 * Le message est signé (HMAC sha256) avec l'empreinte du jeton de l'API publique, que le site recalcule
 * à partir du jeton qu'il possède : aucun secret de plus, et personne d'autre ne peut vider son cache.
 * Un seul envoi par requête, après la réponse (un import de 300 doctorants ne fait qu'un appel).
 */
class SiteVitrine
{
    public static function signalerChangement(): void
    {
        $adresse = config('services.site_vitrine.url');
        if (blank($adresse) || JetonApiPublique::empreinte() === null) {
            return;
        }

        defer(function () use ($adresse): void {
            $cle = JetonApiPublique::empreinte();
            if ($cle === null) {
                return;
            }
            $corps = json_encode(['evenement' => 'donnees_modifiees', 'horodatage' => now()->getTimestamp()]);

            try {
                Http::timeout(5)
                    ->withHeaders(['X-Edgvm-Signature' => 'sha256='.hash_hmac('sha256', (string) $corps, $cle)])
                    ->withBody((string) $corps, 'application/json')
                    ->post(rtrim((string) $adresse, '/').'/api/scolarite/actualiser');
            } catch (Throwable $exception) {
                Log::warning('Site vitrine non prévenu', ['message' => $exception->getMessage()]);
            }
        }, 'site-vitrine');
    }
}
