<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\DeuxFacteurs;
use App\Support\ReglagesApplication;
use App\Support\Totp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Double authentification dans « Mon compte » : préparation (QR code), confirmation, codes de secours, désactivation.
 * L'administration peut la désactiver pour un compte (téléphone perdu).
 */
class DeuxFacteursController extends Controller
{
    public function etat(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => [
            'active' => $user->deuxFacteursActive(),
            'active_le' => $user->deux_facteurs_active_le?->toIso8601String(),
            'codes_secours_restants' => DeuxFacteurs::codesSecoursRestants($user),
        ]]);
    }

    /**
     * Nouveau secret (pas encore actif) : QR code à scanner et clé à saisir à la main.
     */
    public function preparer(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user->deuxFacteursActive(), 409, 'La double authentification est déjà active.');

        $secret = Totp::nouveauSecret();
        $user->forceFill(['deux_facteurs_secret' => $secret, 'deux_facteurs_dernier_pas' => null])->save();
        $uri = Totp::uri($secret, $user->email, ReglagesApplication::texte('app.nom'));

        return response()->json(['data' => [
            'qr' => DeuxFacteurs::qrCode($uri),
            'cle' => trim(chunk_split($secret, 4, ' ')),
            'uri' => $uri,
        ]]);
    }

    /**
     * Premier code de l'appli : la double authentification est active, les codes de secours sont donnés (une seule fois).
     */
    public function confirmer(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'string']], ['code.required' => 'Saisissez le code à 6 chiffres de l\'appli.']);
        $user = $request->user();
        abort_if($user->deuxFacteursActive(), 409, 'La double authentification est déjà active.');

        $pas = $user->deux_facteurs_secret ? Totp::verifier($user->deux_facteurs_secret, $request->string('code')->toString()) : null;
        if ($pas === null) {
            return response()->json(['message' => 'Code incorrect. Vérifiez l\'heure du téléphone et saisissez le code affiché.', 'errors' => ['code' => ['Code incorrect.']]], 422);
        }

        $user->forceFill(['deux_facteurs_active_le' => now(), 'deux_facteurs_dernier_pas' => $pas])->save();

        return response()->json(['data' => ['codes_secours' => DeuxFacteurs::nouveauxCodesSecours($user)], 'message' => 'Double authentification activée.']);
    }

    public function regenererCodes(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'current_password']], ['password.current_password' => 'Mot de passe incorrect.', 'password.required' => 'Saisissez votre mot de passe.']);
        abort_unless($request->user()->deuxFacteursActive(), 409, 'La double authentification n\'est pas active.');

        return response()->json(['data' => ['codes_secours' => DeuxFacteurs::nouveauxCodesSecours($request->user())], 'message' => 'Nouveaux codes de secours : les anciens ne fonctionnent plus.']);
    }

    public function desactiver(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'current_password']], ['password.current_password' => 'Mot de passe incorrect.', 'password.required' => 'Saisissez votre mot de passe.']);
        DeuxFacteurs::desactiver($request->user());

        return response()->json(['message' => 'Double authentification désactivée.']);
    }

    /** Administration : pour un compte dont le téléphone est perdu */
    public function reinitialiserCompte(User $compte): JsonResponse
    {
        $this->authorize('update', $compte);
        DeuxFacteurs::desactiver($compte);

        return response()->json(['message' => "Double authentification désactivée pour {$compte->name}."]);
    }
}
