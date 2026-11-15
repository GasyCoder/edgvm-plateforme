<?php

namespace App\Support;

use App\Enums\StatutInscription;
use App\Models\AnneeUniversitaire;
use App\Models\Doctorant;
use App\Models\Inscription;
use App\Models\ModeleActe;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Variables disponibles dans les modèles d'actes (« {{nom_complet}} ») et remplacement par les valeurs du doctorant.
 */
class GabaritActe
{
    /**
     * Variable => [libellé, exemple affiché dans l'éditeur et l'aperçu]
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const VARIABLES = [
        'civilite' => ['Civilité (Mr ou Mme)', 'Mr'],
        'nom_complet' => ['Nom et prénoms', 'RAKOTO Jean Marie'],
        'nom' => ['Nom', 'RAKOTO'],
        'prenoms' => ['Prénoms', 'Jean Marie'],
        'matricule' => ['Matricule', 'EDGVM-2026-0001'],
        'date_naissance' => ['Date de naissance', '23/09/1991'],
        'lieu_naissance' => ['Lieu de naissance', 'Mahajanga'],
        'ne' => ['« né » ou « née »', 'né'],
        'inscrit' => ['« inscrit » ou « inscrite »', 'inscrit'],
        'admis' => ['« admis » ou « admise »', 'admis'],
        'niveau' => ['Niveau (D1, D2, D3 ou dérogation)', 'D1'],
        'annee_universitaire' => ['Année universitaire', '2026-2027'],
        'annee_debut' => ['Année de début de la formation', '2026'],
        'annee_fin' => ['Année de fin prévue (début + 3 ans)', '2029'],
        'sujet_these' => ['Titre de la thèse', 'Valorisation des plantes médicinales du Boeny'],
        'equipe' => ["Équipe d'accueil (nom)", 'Sciences des Sociétés et Sciences de l\'homme (3SH)'],
        'equipe_sigle' => ["Équipe d'accueil (sigle)", 'EAD 7'],
        'directeur_these' => ['Directeur de thèse', 'Pr RABE Hery'],
        'co_directeur' => ['Co-directeur', 'Pr RASOA Mialy'],
        'co_encadrant' => ['Co-encadrant', ''],
        'date_du_jour' => ['Date de délivrance', '02 mars 2026'],
        'ville' => ['Ville', 'Mahajanga'],
        'numero_acte' => ["Numéro de l'acte", 'EDGVM/2026/000001'],
    ];

    /**
     * @param  array<string, mixed>  $champs  valeurs des champs complémentaires du modèle
     * @return array<string, string>
     */
    public static function valeurs(Doctorant $doctorant, ?Inscription $inscription, ?AnneeUniversitaire $annee, ModeleActe $modele, array $champs, string $numero, CarbonInterface $date, string $ville): array
    {
        $feminin = $doctorant->genre === 'F';
        $inscription ??= $doctorant->inscriptionCourante;
        $premiere = $doctorant->inscriptions()
            ->where('statut', '!=', StatutInscription::Annulee->value)
            ->join('annees_universitaires', 'annees_universitaires.id', '=', 'inscriptions.annee_universitaire_id')
            ->orderBy('annees_universitaires.debut')
            ->value('annees_universitaires.debut');
        $debut = $premiere ? Carbon::parse($premiere)->year : ($annee?->debut->year ?? $date->year);

        $valeurs = [
            'civilite' => match ($doctorant->genre) {
                'F' => 'Mme',
                'M' => 'Mr',
                default => '',
            },
            'nom_complet' => $doctorant->nomComplet(),
            'nom' => mb_strtoupper($doctorant->nom),
            'prenoms' => (string) $doctorant->prenoms,
            'matricule' => $doctorant->matricule,
            'date_naissance' => (string) $doctorant->date_naissance?->format('d/m/Y'),
            'lieu_naissance' => (string) $doctorant->lieu_naissance,
            'ne' => $feminin ? 'née' : 'né',
            'inscrit' => $feminin ? 'inscrite' : 'inscrit',
            'admis' => $feminin ? 'admise' : 'admis',
            'niveau' => $inscription ? ($inscription->est_derogation ? 'D3 (dérogation)' : $inscription->niveau->value) : '',
            'annee_universitaire' => (string) ($annee?->libelle ?? $inscription?->anneeUniversitaire?->libelle),
            'annee_debut' => (string) $debut,
            'annee_fin' => (string) ($debut + 3),
            'sujet_these' => (string) $doctorant->sujet_these,
            'equipe' => (string) $doctorant->equipeAccueil?->nom,
            'equipe_sigle' => (string) $doctorant->equipeAccueil?->sigle,
            'directeur_these' => (string) $doctorant->directeur_these,
            'co_directeur' => (string) $doctorant->co_directeur,
            'co_encadrant' => (string) $doctorant->co_encadrant,
            'date_du_jour' => self::dateLongue($date),
            'ville' => $ville,
            'numero_acte' => $numero,
        ];

        foreach ($modele->champs ?? [] as $champ) {
            $valeurs[$champ['cle']] = self::formaterChamp($champ['type'], $champs[$champ['cle']] ?? null);
        }

        return $valeurs;
    }

    /**
     * Valeurs d'exemple pour l'aperçu d'un modèle sans doctorant.
     *
     * @param  array<int, array{cle: string, libelle: string, type: string}>  $champs
     * @return array<string, string>
     */
    public static function exemples(array $champs, string $ville): array
    {
        $valeurs = array_map(fn (array $variable) => $variable[1], self::VARIABLES);
        $valeurs['date_du_jour'] = self::dateLongue(now());
        $valeurs['ville'] = $ville;

        foreach ($champs as $champ) {
            $valeurs[$champ['cle']] = '['.$champ['libelle'].']';
        }

        return $valeurs;
    }

    /**
     * Remplace les variables par leurs valeurs (échappées) ; une variable vide disparaît.
     *
     * @param  array<string, string>  $valeurs
     */
    public static function remplir(string $texte, array $valeurs, bool $html = true): string
    {
        return preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', function (array $trouve) use ($valeurs, $html): string {
            $valeur = $valeurs[strtolower($trouve[1])] ?? '';

            return $html ? nl2br(e($valeur), false) : $valeur;
        }, $texte);
    }

    /**
     * Variables utilisées dans le texte mais inconnues (ni variable du système, ni champ du modèle).
     *
     * @param  array<int, string>  $champs
     * @return array<int, string>
     */
    public static function variablesInconnues(string $texte, array $champs): array
    {
        preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', $texte, $trouves);

        return array_values(array_unique(array_filter(
            array_map('strtolower', $trouves[1]),
            fn (string $cle) => ! isset(self::VARIABLES[$cle]) && ! in_array($cle, $champs, true),
        )));
    }

    public static function dateLongue(CarbonInterface $date): string
    {
        return $date->copy()->locale('fr')->translatedFormat('d F Y');
    }

    private static function formaterChamp(string $type, mixed $valeur): string
    {
        if (blank($valeur)) {
            return '';
        }

        return match ($type) {
            'date' => self::dateLongue(Carbon::parse((string) $valeur)),
            'heure' => str_replace(':', 'h', substr((string) $valeur, 0, 5)),
            default => (string) $valeur,
        };
    }
}
