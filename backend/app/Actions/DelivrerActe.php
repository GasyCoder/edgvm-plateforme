<?php

namespace App\Actions;

use App\Enums\StatutInscription;
use App\Exceptions\ActeImpossible;
use App\Models\Acte;
use App\Models\AnneeUniversitaire;
use App\Models\Doctorant;
use App\Models\ModeleActe;
use App\Models\User;
use App\Support\GabaritActe;
use App\Support\NettoyeurHtml;
use App\Support\ReglagesActes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Délivre un acte à un doctorant : contrôles, numéro unique, texte figé, code de vérification.
 */
class DelivrerActe
{
    /**
     * @param  array<string, mixed>  $champs
     *
     * @throws ActeImpossible
     */
    public function __invoke(ModeleActe $modele, Doctorant $doctorant, ?AnneeUniversitaire $annee, array $champs, User $par): Acte
    {
        if (! $modele->actif) {
            throw new ActeImpossible("Le modèle « {$modele->nom} » est désactivé.");
        }

        $inscription = null;

        if ($modele->exige_inscription) {
            if (! $annee) {
                throw new ActeImpossible('Choisissez l\'année universitaire.');
            }

            $inscription = $doctorant->inscriptions()->with('anneeUniversitaire')->where('annee_universitaire_id', $annee->id)->first();

            if (! $inscription || $inscription->statut === StatutInscription::Annulee) {
                throw new ActeImpossible("Pas d'inscription pour {$annee->libelle}.");
            }

            if ($inscription->statut !== StatutInscription::Validee) {
                throw new ActeImpossible("L'inscription {$annee->libelle} n'est pas encore validée.");
            }

            if ($inscription->derogationEnAttenteDeDecision()) {
                throw new ActeImpossible("Dérogation {$annee->libelle} sans décision enregistrée.");
            }
        }

        $doctorant->loadMissing(['equipeAccueil']);

        return DB::transaction(function () use ($modele, $doctorant, $annee, $inscription, $champs, $par): Acte {
            $date = now();
            $numero = Acte::prochainNumero($date->year);
            $signataire = ReglagesActes::instantane($modele->inclure_signature);
            $valeurs = GabaritActe::valeurs($doctorant, $inscription, $annee, $modele, $champs, $numero, $date, $signataire['ville']);

            return Acte::query()->create([
                'numero' => $numero,
                'code_verification' => Str::random(32),
                'modele_acte_id' => $modele->id,
                'doctorant_id' => $doctorant->id,
                'annee_universitaire_id' => $annee?->id,
                'inscription_id' => $inscription?->id,
                'titre' => GabaritActe::remplir($modele->titre, $valeurs, html: false),
                'contenu' => NettoyeurHtml::nettoyer(GabaritActe::remplir($modele->corps, $valeurs)),
                'signataire' => $signataire,
                'donnees' => $champs ?: null,
                'delivre_par' => $par->id,
                'delivre_le' => $date,
            ]);
        });
    }
}
