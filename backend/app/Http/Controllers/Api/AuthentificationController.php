<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConnexionRequest;
use App\Http\Requests\ModifierMotDePasseRequest;
use App\Http\Requests\MotDePasseOublieRequest;
use App\Http\Requests\ReinitialiserMotDePasseRequest;
use App\Http\Resources\UtilisateurResource;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthentificationController extends Controller
{
    public function connexion(ConnexionRequest $request): UtilisateurResource
    {
        $identifiants = ['email' => mb_strtolower(trim($request->string('email'))), 'password' => $request->string('password')->toString()];

        if (! Auth::guard('web')->attempt($identifiants, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'Adresse e-mail ou mot de passe incorrect.']);
        }

        /** @var User $user */
        $user = Auth::guard('web')->user();

        if (! $user->actif) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages(['email' => 'Ce compte est désactivé. Contactez la scolarité de l\'École.']);
        }

        $request->session()->regenerate();
        $user->forceFill(['derniere_connexion_le' => now()])->save();

        return new UtilisateurResource($user->load(['doctorant', 'equipesAccueil']));
    }

    public function deconnexion(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function moi(Request $request): UtilisateurResource
    {
        return new UtilisateurResource($request->user()->load(['doctorant', 'equipesAccueil']));
    }

    /**
     * Toujours la même réponse, que l'adresse existe ou non (pas de fuite de comptes).
     */
    public function motDePasseOublie(MotDePasseOublieRequest $request): JsonResponse
    {
        $user = User::query()->where('email', mb_strtolower(trim($request->string('email'))))->where('actif', true)->first();

        if ($user) {
            Password::broker('users')->sendResetLink(['email' => $user->email]);
        }

        return response()->json(['message' => 'Si un compte correspond à cette adresse, un lien vient de lui être envoyé.']);
    }

    /**
     * Le lien de premier accès (7 jours) et celui de « mot de passe oublié » (1 heure) passent ici.
     */
    public function reinitialiserMotDePasse(ReinitialiserMotDePasseRequest $request): JsonResponse
    {
        $email = mb_strtolower(trim($request->string('email')));
        $user = User::query()->where('email', $email)->first();
        $broker = $user && $user->derniere_connexion_le === null ? 'invitations' : 'users';

        $statut = Password::broker($broker)->reset(
            ['email' => $email, 'password' => $request->string('password')->toString(), 'password_confirmation' => $request->string('password_confirmation')->toString(), 'token' => $request->string('token')->toString()],
            function (User $compte, string $motDePasse): void {
                $compte->forceFill(['password' => $motDePasse, 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($compte));
            },
        );

        if ($statut !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($statut)]);
        }

        return response()->json(['message' => 'Votre mot de passe est enregistré. Vous pouvez vous connecter.']);
    }

    public function modifierMotDePasse(ModifierMotDePasseRequest $request): JsonResponse
    {
        $request->user()->update(['password' => $request->string('password')->toString()]);

        return response()->json(['message' => 'Mot de passe modifié.']);
    }
}
