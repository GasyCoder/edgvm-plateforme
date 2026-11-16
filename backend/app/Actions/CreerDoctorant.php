<?php

namespace App\Actions;

use App\Enums\RoleUtilisateur;
use App\Enums\StatutDoctorant;
use App\Enums\StatutInscription;
use App\Enums\TypeInscription;
use App\Models\Doctorant;
use App\Models\User;
use App\Support\NumeroMatricule;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Crée le compte, le dossier et, si fournie, la première inscription d'un doctorant.
 * Utilisée par le formulaire et par l'import.
 */
class CreerDoctorant
{
    public function __construct(private EnvoyerInvitation $envoyerInvitation, private AppliquerFraisInscription $appliquerFrais) {}

    /**
     * @param  array<string, mixed>  $donnees  données validées (DoctorantRequest ou ligne d'import)
     */
    public function __invoke(array $donnees, bool $envoyerInvitation = true): Doctorant
    {
        $candidat = ($donnees['statut'] ?? null) === StatutDoctorant::Candidat->value;
        $envoyerInvitation = $envoyerInvitation && ! $candidat;

        $doctorant = DB::transaction(function () use ($donnees, $candidat): Doctorant {
            /* Un candidat n'a pas encore accès à la plateforme : son compte s'active à l'admission. */
            $user = User::query()->create([
                'name' => trim(mb_strtoupper($donnees['nom']).' '.($donnees['prenoms'] ?? '')),
                'email' => $donnees['email'],
                'telephone' => $donnees['telephone'] ?? null,
                'password' => Str::password(32),
                'role' => RoleUtilisateur::Doctorant,
                'actif' => ! $candidat,
            ]);

            $doctorant = $user->doctorant()->create([
                ...Arr::except($donnees, ['email', 'telephone', 'inscription', 'envoyer_invitation', 'matricule', 'statut']),
                'matricule' => match (true) {
                    filled($donnees['matricule'] ?? null) => $donnees['matricule'],
                    $candidat => NumeroMatricule::provisoire(),
                    default => Doctorant::prochainMatricule(),
                },
                'statut' => $donnees['statut'] ?? StatutDoctorant::Actif->value,
                'candidature_le' => $candidat ? now()->toDateString() : null,
            ]);

            if (filled($donnees['inscription'] ?? null) && ! $candidat) {
                $inscription = $doctorant->inscriptions()->create([
                    ...$donnees['inscription'],
                    'type' => TypeInscription::Premiere,
                    'statut' => StatutInscription::Validee,
                    'date_inscription' => now()->toDateString(),
                ]);
                ($this->appliquerFrais)($inscription, auth()->user());
            }

            return $doctorant;
        });

        if ($envoyerInvitation) {
            ($this->envoyerInvitation)($doctorant->user);
        }

        return $doctorant;
    }
}
