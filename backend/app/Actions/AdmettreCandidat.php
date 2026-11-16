<?php

namespace App\Actions;

use App\Enums\DecisionCandidature;
use App\Enums\NiveauDoctorat;
use App\Enums\StatutDoctorant;
use App\Enums\StatutInscription;
use App\Enums\TypeInscription;
use App\Exceptions\ActeImpossible;
use App\Exceptions\AdmissionImpossible;
use App\Models\Acte;
use App\Models\AnneeUniversitaire;
use App\Models\Doctorant;
use App\Models\ModeleActe;
use App\Models\Passage;
use App\Models\User;
use App\Notifications\AdmissionPrononcee;
use App\Support\NumeroMatricule;
use Illuminate\Support\Facades\DB;

/**
 * Le candidat admis par le jury devient doctorant, en une seule opération :
 * statut actif et vrai matricule, inscription en D1 pour l'année en cours avec ses droits d'inscription,
 * attestation d'admission délivrée, compte activé avec lien d'accès, notification (e-mail avec l'attestation).
 */
class AdmettreCandidat
{
    public const MODELE_ADMISSION = 'admission-doctorat';

    public function __construct(
        private AppliquerFraisInscription $appliquerFrais,
        private DelivrerActe $delivrerActe,
        private EnvoyerInvitation $envoyerInvitation,
    ) {}

    /**
     * @return array{doctorant: Doctorant, acte: ?Acte, droits: int}
     *
     * @throws AdmissionImpossible
     */
    public function __invoke(Doctorant $doctorant, User $par, ?Passage $passage = null): array
    {
        if (! $doctorant->estCandidat()) {
            throw new AdmissionImpossible("{$doctorant->nomComplet()} n'est pas candidat.");
        }

        if ($doctorant->decision_candidature !== DecisionCandidature::Admis) {
            throw new AdmissionImpossible("Le jury n'a pas déclaré {$doctorant->nomComplet()} admis.");
        }

        $annee = AnneeUniversitaire::courante();
        if (! $annee) {
            throw new AdmissionImpossible("Aucune année universitaire en cours : impossible d'inscrire en D1.");
        }

        $passage ??= $doctorant->passages()->with('seance')->where('decision', DecisionCandidature::Admis->value)->latest('debut')->first();

        $resultat = DB::transaction(function () use ($doctorant, $par, $annee, $passage): array {
            $doctorant->update([
                'statut' => StatutDoctorant::Actif,
                'matricule' => NumeroMatricule::estProvisoire($doctorant->matricule) ? NumeroMatricule::suivant() : $doctorant->matricule,
                'admis_le' => now(),
            ]);
            $doctorant->user->update(['actif' => true]);

            $inscription = $doctorant->inscriptions()->firstOrCreate(
                ['annee_universitaire_id' => $annee->id],
                [
                    'niveau' => NiveauDoctorat::D1,
                    'type' => TypeInscription::Premiere,
                    'statut' => StatutInscription::Validee,
                    'date_inscription' => now()->toDateString(),
                ],
            );
            if ($inscription->wasRecentlyCreated) {
                ($this->appliquerFrais)($inscription, $par);
            }
            $droits = (int) $doctorant->frais()->where('inscription_id', $inscription->id)->whereNull('annule_le')->sum('montant');

            $acte = null;
            $modele = ModeleActe::query()->where('code', self::MODELE_ADMISSION)->where('actif', true)->first();
            if ($modele) {
                try {
                    $acte = ($this->delivrerActe)($modele, $doctorant->refresh(), $annee, [
                        'date_presentation' => ($passage?->debut ?? now())->toDateString(),
                    ], $par);
                } catch (ActeImpossible) {
                    $acte = null;
                }
            }

            if ($passage) {
                $passage->update(['decision_notifiee_le' => now()]);
            }

            return ['doctorant' => $doctorant->refresh(), 'acte' => $acte, 'droits' => $droits];
        });

        ($this->envoyerInvitation)($doctorant->user);
        $doctorant->user->notify(new AdmissionPrononcee($resultat['doctorant'], $resultat['acte'], $resultat['droits']));

        return $resultat;
    }
}
