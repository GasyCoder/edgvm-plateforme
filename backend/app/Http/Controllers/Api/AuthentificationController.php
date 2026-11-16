<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConnexionRequest;
use App\Http\Requests\ModifierMotDePasseRequest;
use App\Http\Requests\MotDePasseOublieRequest;
use App\Http\Requests\ReinitialiserMotDePasseRequest;
use App\Http\Resources\UtilisateurResource;
use App\Models\User;
use App\Support\DeuxFacteurs;
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
    private const DEUX_FACTEURS = 'connexion.deux_facteurs';

    /**
     * Si la double authentification est active, le mot de passe ne suffit pas : la réponse demande le code
     * de l'appli (étape suivante : deuxFacteurs), sans ouvrir de session.
     */
    public function connexion(ConnexionRequest $request): UtilisateurResource|JsonResponse
    {
        $identifiants = ['email' => mb_strtolower(trim($request->string('email'))), 'password' => $request->string('password')->toString()];

        if (! Auth::guard('web')->validate($identifiants)) {
            throw ValidationException::withMessages(['email' => 'Adresse e-mail ou mot de passe incorrect.']);
        }

        /** @var User $user */
        $user = User::query()->where('email', $identifiants['email'])->firstOrFail();

        if (! $user->actif) {
            throw ValidationException::withMessages(['email' => 'Ce compte est désactivé. Contactez la scolarité de l\'École.']);
        }

        if ($user->deuxFacteursActive()) {
            $request->session()->put(self::DEUX_FACTEURS, ['id' => $user->id, 'remember' => $request->boolean('remember'), 'expire' => now()->addMinutes(5)->getTimestamp(), 'essais' => 0]);

            return response()->json(['deux_facteurs' => true, 'message' => 'Saisissez le code de votre appli d\'authentification.']);
        }

        return $this->ouvrirSession($request, $user, $request->boolean('remember'));
    }

    /**
     * Deuxième étape : code de l'appli (ou code de secours), dans les 5 minutes, 5 essais au plus.
     */
    public function deuxFacteurs(Request $request): UtilisateurResource|JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:20']], ['code.required' => 'Saisissez le code de votre appli, ou un code de secours.']);
        $attente = $request->session()->get(self::DEUX_FACTEURS);

        if (! is_array($attente) || $attente['expire'] < now()->getTimestamp() || $attente['essais'] >= 5) {
            $request->session()->forget(self::DEUX_FACTEURS);

            return response()->json(['message' => 'Délai dépassé : reconnectez-vous avec votre mot de passe.', 'recommencer' => true], 422);
        }

        $user = User::query()->where('actif', true)->find($attente['id']);
        if (! $user || ! DeuxFacteurs::verifier($user, $request->string('code')->toString())) {
            $request->session()->put(self::DEUX_FACTEURS, [...$attente, 'essais' => $attente['essais'] + 1]);
            $reste = 4 - $attente['essais'];

            return response()->json(['message' => 'Code incorrect.', 'errors' => ['code' => [$reste > 0 ? "Code incorrect. Il reste {$reste} essai".($reste > 1 ? 's' : '').'.' : 'Code incorrect.']]], 422);
        }

        $request->session()->forget(self::DEUX_FACTEURS);

        return $this->ouvrirSession($request, $user, (bool) $attente['remember']);
    }

    private function ouvrirSession(Request $request, User $user, bool $souvenir): UtilisateurResource
    {
        Auth::guard('web')->login($user, $souvenir);
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
