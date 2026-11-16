<?php

namespace App\Actions;

use App\Enums\StatutDoctorant;
use App\Http\Requests\DoctorantRequest;
use App\Models\AnneeUniversitaire;
use App\Models\EquipeAccueil;
use App\Support\FichierCsv;
use App\Support\Telephone;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Lit un fichier d'import de doctorants et vérifie chaque ligne avec les mêmes règles que le formulaire.
 */
class AnalyserImportDoctorants
{
    public const MAX_LIGNES = 1000;

    /** Colonnes reconnues : en-tête normalisé => champ */
    public const COLONNES = [
        'nom' => 'nom',
        'prenoms' => 'prenoms', 'prenom' => 'prenoms',
        'genre' => 'genre', 'sexe' => 'genre',
        'date_de_naissance' => 'date_naissance', 'date_naissance' => 'date_naissance', 'ne_le' => 'date_naissance',
        'lieu_de_naissance' => 'lieu_naissance', 'lieu_naissance' => 'lieu_naissance',
        'nationalite' => 'nationalite',
        'cin_ou_passeport' => 'numero_piece', 'cin' => 'numero_piece', 'passeport' => 'numero_piece', 'numero_piece' => 'numero_piece',
        'adresse' => 'adresse',
        'e_mail' => 'email', 'email' => 'email', 'mail' => 'email', 'adresse_e_mail' => 'email',
        'telephone' => 'telephone', 'tel' => 'telephone', 'contact' => 'telephone',
        'equipe' => 'equipe', 'equipe_d_accueil' => 'equipe', 'ead' => 'equipe',
        'sujet_de_these' => 'sujet_these', 'sujet' => 'sujet_these', 'sujet_these' => 'sujet_these',
        'directeur_de_these' => 'directeur_these', 'directeur' => 'directeur_these', 'directeur_these' => 'directeur_these',
        'co_directeur' => 'co_directeur', 'codirecteur' => 'co_directeur',
        'co_encadrant' => 'co_encadrant', 'coencadrant' => 'co_encadrant',
        'matricule' => 'matricule',
        'niveau' => 'niveau',
        'boursier' => 'est_boursier', 'bourse' => 'est_boursier', 'est_boursier' => 'est_boursier',
    ];

    /**
     * @return array{lignes: array<int, array{ligne: int, donnees: array<string, mixed>, erreurs: array<int, string>, libelle: string}>, erreur_fichier: ?string}
     */
    /**
     * Avec « $candidats », chaque ligne devient un candidat : ni niveau ni matricule (numéro provisoire, inscription à l'admission).
     */
    public function __invoke(string $chemin, bool $candidats = false): array
    {
        $lignes = FichierCsv::lire($chemin);

        if ($lignes === []) {
            return ['lignes' => [], 'erreur_fichier' => 'Le fichier est vide ou ne contient que la ligne des titres.'];
        }

        $champs = array_values(array_unique(array_filter(array_map(fn (string $entete) => self::COLONNES[$entete] ?? null, array_keys(reset($lignes))))));

        foreach (['nom', 'email', 'equipe'] as $obligatoire) {
            if (! in_array($obligatoire, $champs, true)) {
                return ['lignes' => [], 'erreur_fichier' => "Colonne obligatoire absente : « {$obligatoire} ». Partez du modèle d'import."];
            }
        }

        if (count($lignes) > self::MAX_LIGNES) {
            return ['lignes' => [], 'erreur_fichier' => 'Le fichier dépasse '.self::MAX_LIGNES.' lignes : découpez-le en plusieurs fichiers.'];
        }

        $equipes = EquipeAccueil::query()->get(['id', 'sigle'])->mapWithKeys(fn (EquipeAccueil $equipe) => [self::cleSigle($equipe->sigle) => $equipe->id]);
        $annee = AnneeUniversitaire::courante();
        $vus = ['email' => [], 'matricule' => []];
        $resultat = [];

        foreach ($lignes as $numero => $brute) {
            $valeurs = [];
            foreach ($brute as $entete => $valeur) {
                if (isset(self::COLONNES[$entete]) && $valeur !== '') {
                    $valeurs[self::COLONNES[$entete]] ??= $valeur;
                }
            }

            if ($candidats) {
                unset($valeurs['niveau'], $valeurs['matricule']);
            }

            [$donnees, $erreurs, $dejaSignales] = $this->preparer($valeurs, $equipes->all(), $annee);

            if ($candidats) {
                $donnees['statut'] = StatutDoctorant::Candidat->value;
            }

            $validation = Validator::make($donnees, DoctorantRequest::regles(), DoctorantRequest::messagesValidation(), DoctorantRequest::nomsDesChamps());
            $messages = collect($validation->errors()->messages())->except($dejaSignales)->flatten()->all();
            $erreurs = [...$erreurs, ...$messages];

            foreach (['email', 'matricule'] as $unique) {
                $cle = mb_strtolower((string) ($donnees[$unique] ?? ''));
                if ($cle !== '' && isset($vus[$unique][$cle])) {
                    $erreurs[] = ($unique === 'email' ? 'Adresse e-mail' : 'Matricule')." déjà présent ligne {$vus[$unique][$cle]} du fichier.";
                }
                if ($cle !== '') {
                    $vus[$unique][$cle] ??= $numero;
                }
            }

            $resultat[] = [
                'ligne' => $numero,
                'libelle' => trim(mb_strtoupper($donnees['nom'] ?? '').' '.($donnees['prenoms'] ?? '')) ?: 'Ligne sans nom',
                'donnees' => $donnees,
                'erreurs' => array_values(array_unique($erreurs)),
            ];
        }

        return ['lignes' => $resultat, 'erreur_fichier' => null];
    }

    /**
     * Convertit les valeurs saisies dans le tableur au format attendu par les règles du formulaire.
     *
     * @param  array<string, string>  $valeurs
     * @param  array<string, int>  $equipes
     * @return array{0: array<string, mixed>, 1: array<int, string>, 2: array<int, string>} données, erreurs, champs déjà signalés
     */
    private function preparer(array $valeurs, array $equipes, ?AnneeUniversitaire $annee): array
    {
        $erreurs = [];
        $signales = [];
        $donnees = collect($valeurs)->only(['nom', 'prenoms', 'lieu_naissance', 'nationalite', 'numero_piece', 'adresse', 'sujet_these', 'directeur_these', 'co_directeur', 'co_encadrant', 'matricule'])->all();
        $donnees['email'] = isset($valeurs['email']) ? mb_strtolower($valeurs['email']) : null;
        $donnees['telephone'] = Telephone::normaliser($valeurs['telephone'] ?? null);

        if (isset($valeurs['est_boursier'])) {
            $reponse = Str::of($valeurs['est_boursier'])->ascii()->lower()->trim()->toString();
            $donnees['est_boursier'] = match (true) {
                in_array($reponse, ['oui', 'o', 'yes', 'y', '1', 'vrai', 'x', 'boursier', 'boursiere'], true) => true,
                in_array($reponse, ['non', 'n', 'no', '0', 'faux', ''], true) => false,
                default => $valeurs['est_boursier'],
            };
        }

        if (isset($valeurs['genre'])) {
            $genre = Str::of($valeurs['genre'])->ascii()->upper()->substr(0, 1)->toString();
            $donnees['genre'] = match ($genre) {
                'F' => 'F',
                'M', 'H' => 'M',
                default => $valeurs['genre'],
            };
        }

        if (isset($valeurs['date_naissance'])) {
            $date = $this->lireDate($valeurs['date_naissance']);
            if ($date) {
                $donnees['date_naissance'] = $date;
            } else {
                $erreurs[] = "Date de naissance illisible : « {$valeurs['date_naissance']} » (format attendu : 25/03/1990).";
                $signales[] = 'date_naissance';
            }
        }

        if (isset($valeurs['equipe'])) {
            $donnees['equipe_accueil_id'] = $equipes[self::cleSigle($valeurs['equipe'])] ?? null;
            if ($donnees['equipe_accueil_id'] === null) {
                $erreurs[] = "Équipe d'accueil inconnue : « {$valeurs['equipe']} ».";
                $signales[] = 'equipe_accueil_id';
            }
        }

        if (isset($valeurs['niveau'])) {
            if (! $annee) {
                $erreurs[] = "Aucune année universitaire en cours : impossible d'inscrire le doctorant.";
            }
            $donnees['inscription'] = [
                'annee_universitaire_id' => $annee?->id,
                'niveau' => mb_strtoupper(str_replace(' ', '', $valeurs['niveau'])),
            ];
        }

        return [$donnees, $erreurs, $signales];
    }

    private function lireDate(string $valeur): ?string
    {
        foreach (['d/m/Y', 'j/n/Y', 'd-m-Y', 'Y-m-d', 'd.m.Y'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, $valeur);
            } catch (\Throwable) {
                continue;
            }
            if ($date && $date->format($format) === $valeur) {
                return $date->toDateString();
            }
        }

        return null;
    }

    /** « EAD 1 », « ead1 » et « EAD-1 » désignent la même équipe. */
    private static function cleSigle(string $sigle): string
    {
        return Str::of($sigle)->ascii()->upper()->replaceMatches('/[^A-Z0-9]/', '')->toString();
    }
}
