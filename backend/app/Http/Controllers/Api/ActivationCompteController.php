<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleUtilisateur;
use App\Http\Controllers\Controller;
use App\Http\Resources\UtilisateurResource;
use App\Models\CodeVerification;
use App\Models\User;
use App\Notifications\CodeActivation;
use App\Support\Telephone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Throwable;

use function Illuminate\Support\defer;

/**
 * « Créer mon accès » : un doctorant ou un membre d'équipe, déjà enregistré par la scolarité, active son compte
 * avec son e-mail ou son numéro : code à 4 chiffres envoyé à son adresse e-mail, puis choix du mot de passe.
 */
class ActivationCompteController extends Controller
{
    public function demander(Request $request): JsonResponse
    {
        $request->validate(['identifiant' => ['required', 'string', 'max:190']], ['identifiant.required' => 'Indiquez votre adresse e-mail ou votre numéro de téléphone.']);
        $identifiant = trim($request->string('identifiant')->toString());
        $parEmail = str_contains($identifiant, '@');

        $comptes = User::query()
            ->whereIn('role', [RoleUtilisateur::Doctorant->value, RoleUtilisateur::MembreEad->value])
            ->where('actif', true)
            ->when(
                $parEmail,
                fn ($q) => $q->where('email', mb_strtolower($identifiant)),
                fn ($q) => $q->where('telephone', Telephone::normaliser($identifiant) ?? '-'),
            )
            ->limit(2)
            ->get();

        if ($comptes->isEmpty()) {
            return $this->refus('identifiant', $parEmail
                ? 'Aucun compte ne correspond à cette adresse. Vérifiez-la, ou adressez-vous à la scolarité de l\'École.'
                : 'Aucun compte ne correspond à ce numéro. Essayez avec votre adresse e-mail, ou adressez-vous à la scolarité.');
        }

        if ($comptes->count() > 1) {
            return $this->refus('identifiant', 'Plusieurs comptes ont ce numéro : utilisez votre adresse e-mail.');
        }

        /** @var User $user */
        $user = $comptes->first();

        if ($user->derniere_connexion_le !== null) {
            return response()->json([
                'message' => 'Ce compte est déjà activé : connectez-vous, ou utilisez « Mot de passe oublié ».',
                'code' => 'deja_active',
            ], 409);
        }

        CodeVerification::query()->where('user_id', $user->id)->whereNull('utilise_le')->delete();
        $code = self::nouveauCode();
        $demande = CodeVerification::query()->create([
            'user_id' => $user->id,
            /* Toujours par e-mail, même si la personne s'est identifiée par son numéro de téléphone. */
            'canal' => 'email',
            'code_hash' => self::empreinte($code),
            'expire_le' => now()->addMinutes(CodeVerification::DUREE_MINUTES),
            'dernier_envoi_le' => now(),
        ]);

        if ($erreur = $this->envoyer($user, $demande, $code)) {
            $demande->delete();

            return $erreur;
        }

        return self::reponseImmediate(response()->json(['data' => $this->etat($demande, $user)], 201));
    }

    public function renvoyer(Request $request): JsonResponse
    {
        $demande = $this->demande($request);

        if (! $demande || $demande->verifie_le) {
            return $this->refus('code', 'Cette demande n\'est plus valable : recommencez.');
        }

        if ($attente = $demande->attenteRenvoi()) {
            return response()->json(['message' => "Patientez {$attente} secondes avant de demander un nouveau code.", 'renvoyer_dans' => $attente], 429);
        }

        if ($demande->envois >= CodeVerification::ENVOIS_MAX) {
            return response()->json(['message' => 'Trop de codes envoyés. Réessayez dans une heure, ou adressez-vous à la scolarité.'], 429);
        }

        $code = self::nouveauCode();
        $demande->update([
            'code_hash' => self::empreinte($code),
            'expire_le' => now()->addMinutes(CodeVerification::DUREE_MINUTES),
            'essais' => 0,
            'envois' => $demande->envois + 1,
            'dernier_envoi_le' => now(),
        ]);

        if ($erreur = $this->envoyer($demande->user, $demande, $code)) {
            return $erreur;
        }

        return self::reponseImmediate(response()->json(['data' => $this->etat($demande, $demande->user), 'message' => 'Nouveau code envoyé.']));
    }

    public function verifier(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'digits:4']], ['code.required' => 'Saisissez le code à 4 chiffres.', 'code.digits' => 'Le code a 4 chiffres.']);
        $demande = $this->demande($request);

        if (! $demande || $demande->verifie_le) {
            return $this->refus('code', 'Cette demande n\'est plus valable : recommencez.');
        }

        if ($demande->estExpire()) {
            return $this->refus('code', 'Ce code a expiré : demandez-en un nouveau.');
        }

        if ($demande->essais >= CodeVerification::ESSAIS_MAX) {
            return $this->refus('code', 'Trop d\'essais : demandez un nouveau code.');
        }

        if (! hash_equals($demande->code_hash, self::empreinte($request->string('code')->toString()))) {
            $demande->increment('essais');
            $reste = CodeVerification::ESSAIS_MAX - $demande->essais;

            return $this->refus('code', $reste > 0 ? "Code incorrect. Il reste {$reste} essai".($reste > 1 ? 's' : '').'.' : 'Trop d\'essais : demandez un nouveau code.');
        }

        $jeton = Str::random(48);
        $demande->update(['verifie_le' => now(), 'jeton_hash' => hash('sha256', $jeton)]);

        return response()->json(['data' => ['jeton' => $jeton, 'nom' => $demande->user->name]]);
    }

    /**
     * Mot de passe choisi : le compte est activé et la personne est connectée.
     */
    public function terminer(Request $request): JsonResponse|UtilisateurResource
    {
        $request->validate(
            ['jeton' => ['required', 'string'], 'password' => ['required', 'string', 'confirmed', Password::defaults()]],
            ['password.confirmed' => 'Les deux mots de passe ne sont pas identiques.'],
            ['password' => 'mot de passe'],
        );
        $demande = $this->demande($request);

        if (! $demande || ! $demande->verifie_le || $demande->utilise_le
            || $demande->verifie_le->addMinutes(CodeVerification::DUREE_JETON_MINUTES)->isPast()
            || ! hash_equals((string) $demande->jeton_hash, hash('sha256', $request->string('jeton')->toString()))) {
            return $this->refus('password', 'Le délai est dépassé : recommencez la création de votre accès.');
        }

        $user = $demande->user;
        $user->forceFill(['password' => $request->string('password')->toString(), 'remember_token' => Str::random(60), 'email_verified_at' => $user->email_verified_at ?? now()])->save();
        $demande->update(['utilise_le' => now()]);
        CodeVerification::query()->where('user_id', $user->id)->whereKeyNot($demande->id)->delete();

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $user->forceFill(['derniere_connexion_le' => now()])->save();

        return new UtilisateurResource($user->load(['doctorant', 'equipesAccueil']));
    }

    private function demande(Request $request): ?CodeVerification
    {
        $id = $request->string('demande')->toString();

        return Str::isUuid($id) ? CodeVerification::query()->with('user')->find($id) : null;
    }

    /**
     * L'e-mail part juste après la réponse (le serveur SMTP peut prendre plusieurs secondes) : la page passe
     * aussitôt à la saisie du code.
     */
    private function envoyer(User $user, CodeVerification $demande, string $code): ?JsonResponse
    {
        defer(function () use ($user, $code): void {
            try {
                $user->notifyNow(new CodeActivation($code, 'email'));
            } catch (Throwable $exception) {
                Log::warning('Envoi du code d\'activation par e-mail impossible', ['message' => $exception->getMessage()]);
            }
        });

        return null;
    }

    /**
     * @return array{demande: string, canal: string, destination: string, renvoyer_dans: int, expire_dans: int}
     */
    private function etat(CodeVerification $demande, User $user): array
    {
        return [
            'demande' => $demande->id,
            'canal' => $demande->canal,
            'destination' => self::masquerEmail($user->email),
            'renvoyer_dans' => $demande->attenteRenvoi(),
            'expire_dans' => (int) max(0, now()->diffInSeconds($demande->expire_le, false)),
        ];
    }

    /**
     * Longueur exacte et fermeture de la connexion : le navigateur a sa réponse sans attendre l'envoi de l'e-mail,
     * même avec le serveur de développement de PHP (en production, PHP-FPM ou LiteSpeed ferment déjà la connexion).
     */
    private static function reponseImmediate(JsonResponse $reponse): JsonResponse
    {
        $reponse->headers->set('Content-Length', (string) strlen((string) $reponse->getContent()));
        $reponse->headers->set('Connection', 'close');

        return $reponse;
    }

    private function refus(string $champ, string $message, int $statut = 422): JsonResponse
    {
        return response()->json(['message' => $message, 'errors' => [$champ => [$message]]], $statut);
    }

    /**
     * Empreinte du code (HMAC avec la clé de l'application) : instantanée, et le code n'est jamais stocké en clair.
     */
    private static function empreinte(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    private static function nouveauCode(): string
    {
        return str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    /** « fanja.rabe@gmail.com » devient « f•••••••e@gmail.com » */
    public static function masquerEmail(string $email): string
    {
        [$nom, $domaine] = explode('@', $email, 2) + [1 => ''];

        return mb_substr($nom, 0, 1).str_repeat('•', max(2, mb_strlen($nom) - 2)).(mb_strlen($nom) > 2 ? mb_substr($nom, -1) : '').'@'.$domaine;
    }
}
