<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * Lien de téléchargement signé, valable quelques minutes, pour l'application mobile :
 * le PDF s'ouvre dans le navigateur du téléphone, qui n'a pas la session de l'application.
 * La signature couvre l'utilisateur, l'expiration, le chemin et tous les paramètres.
 */
class LienTelechargement
{
    public const DUREE_SECONDES = 300;

    private const PARAMETRES = ['_u', '_exp', '_sig'];

    public static function signer(string $chemin, User $user): string
    {
        $morceaux = parse_url($chemin);
        parse_str($morceaux['query'] ?? '', $parametres);
        $expiration = now()->addSeconds(self::DUREE_SECONDES)->getTimestamp();
        $parametres = Arr::except($parametres, self::PARAMETRES);
        $signature = self::signature($user->getKey(), $expiration, $morceaux['path'] ?? '/', $parametres);

        return url($morceaux['path'] ?? '/').'?'.Arr::query([...$parametres, '_u' => $user->getKey(), '_exp' => $expiration, '_sig' => $signature]);
    }

    /**
     * Identifiant de l'utilisateur si le lien est intact et pas encore expiré.
     */
    public static function utilisateur(Request $request): ?int
    {
        $id = $request->query('_u');
        $expiration = $request->query('_exp');
        $signature = $request->query('_sig');
        if (! is_string($id) || ! is_string($expiration) || ! is_string($signature) || ! ctype_digit($id) || ! ctype_digit($expiration)) {
            return null;
        }
        if ((int) $expiration < now()->getTimestamp()) {
            return null;
        }
        $attendue = self::signature((int) $id, (int) $expiration, '/'.ltrim($request->path(), '/'), Arr::except($request->query(), self::PARAMETRES));

        return hash_equals($attendue, $signature) ? (int) $id : null;
    }

    /**
     * @param  array<string, mixed>  $parametres
     */
    private static function signature(int|string $id, int $expiration, string $chemin, array $parametres): string
    {
        ksort($parametres);
        $canonique = implode('|', [$id, $expiration, rawurldecode('/'.ltrim($chemin, '/')), Arr::query($parametres)]);

        return hash_hmac('sha256', $canonique, (string) config('app.key'));
    }
}
