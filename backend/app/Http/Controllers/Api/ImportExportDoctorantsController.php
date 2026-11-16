<?php

namespace App\Http\Controllers\Api;

use App\Actions\AnalyserImportDoctorants;
use App\Actions\CreerDoctorant;
use App\Http\Controllers\Controller;
use App\Models\AnneeUniversitaire;
use App\Models\Doctorant;
use App\Support\FichierCsv;
use App\Support\FichierExcel;
use App\Support\SituationFinanciere;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export de la liste (filtres appliqués, Excel ou CSV) et import en masse des doctorants (fichier CSV, simulation d'abord).
 */
class ImportExportDoctorantsController extends Controller
{
    /**
     * Les membres d'équipe exportent leurs doctorants sans les données personnelles ni les montants.
     */
    public function exporter(Request $request): StreamedResponse
    {
        [$entetes, $lignes] = $this->donneesExport($request);

        return FichierCsv::telecharger('doctorants-edgvm-'.now()->format('Y-m-d').'.csv', $entetes, $lignes);
    }

    public function exporterExcel(Request $request): StreamedResponse
    {
        [$entetes, $lignes] = $this->donneesExport($request);

        return FichierExcel::telecharger('doctorants-edgvm-'.now()->format('Y-m-d').'.xlsx', $entetes, $lignes, 'Doctorants');
    }

    /**
     * En-têtes et lignes de l'export, filtres de la liste appliqués.
     *
     * @return array{0: array<int, string>, 1: Collection<int, array<int, mixed>>}
     */
    private function donneesExport(Request $request): array
    {
        $this->authorize('viewAny', Doctorant::class);
        $complet = $request->user()->estSuperAdmin();
        $annee = AnneeUniversitaire::courante();

        $doctorants = SituationFinanciere::avecTotaux(DoctorantController::requeteFiltree($request))
            ->when($request->filled('ids'), fn ($requete) => $requete->whereIn('uuid', (array) $request->input('ids')))
            ->get();

        $entetes = ['Matricule', 'Nom', 'Prénoms', 'Genre', 'E-mail', 'Téléphone', 'Équipe', 'Sujet de thèse', 'Boursier', 'Statut', 'Niveau '.($annee?->libelle ?? ''), 'Inscription'];
        if ($complet) {
            array_splice($entetes, 4, 0, ['Date de naissance', 'Lieu de naissance', 'Nationalité', 'CIN ou passeport', 'Adresse']);
            array_push($entetes, 'Dû (Ar)', 'Payé (Ar)', 'Reste (Ar)', 'Paiement');
        }

        $lignes = $doctorants->map(function (Doctorant $doctorant) use ($complet): array {
            $inscription = $doctorant->inscriptionCourante;
            $ligne = [
                $doctorant->matricule,
                mb_strtoupper($doctorant->nom),
                $doctorant->prenoms,
                $doctorant->genre,
                $doctorant->user->email,
                $doctorant->user->telephone,
                $doctorant->equipeAccueil?->sigle,
                $doctorant->sujet_these,
                $doctorant->est_boursier ? 'Oui' : 'Non',
                $doctorant->statut->libelle(),
                $inscription?->niveauAffiche(),
                $inscription?->statut->libelle() ?? 'Non inscrit',
            ];

            if ($complet) {
                array_splice($ligne, 4, 0, [
                    $doctorant->date_naissance?->format('d/m/Y'),
                    $doctorant->lieu_naissance,
                    $doctorant->nationalite,
                    $doctorant->numero_piece,
                    $doctorant->adresse,
                ]);
                $situation = SituationFinanciere::pour($doctorant);
                array_push($ligne, $situation['du'], $situation['paye'], $situation['reste'], $situation['statut_libelle']);
            }

            return $ligne;
        });

        return [$entetes, $lignes];
    }

    public function modele(Request $request): StreamedResponse
    {
        $this->authorize('create', Doctorant::class);

        return FichierCsv::telecharger('modele-import-doctorants.csv', [
            'Nom', 'Prénoms', 'Genre', 'Date de naissance', 'Lieu de naissance', 'Nationalité', 'CIN ou passeport', 'Adresse',
            'E-mail', 'Téléphone', 'Équipe', 'Sujet de thèse', 'Directeur de thèse', 'Co-directeur', 'Boursier', 'Matricule', 'Niveau',
        ], [[
            'RAKOTO', 'Jean Marie', 'M', '25/03/1990', 'Mahajanga', 'Malagasy', '401011012345', 'Lot II A 12 Mahajanga',
            'jean.rakoto@exemple.mg', '034 12 345 67', 'EAD 1', 'Sujet de la thèse', 'Pr RABE Hery', '', 'Non', '', 'D1',
        ]]);
    }

    /**
     * Modèle d'import des candidats : sans niveau ni matricule (attribués à l'admission).
     */
    public function modeleCandidats(Request $request): StreamedResponse
    {
        $this->authorize('create', Doctorant::class);

        return FichierCsv::telecharger('modele-import-candidats.csv', [
            'Nom', 'Prénoms', 'Genre', 'Date de naissance', 'Lieu de naissance', 'Nationalité', 'CIN ou passeport', 'Adresse',
            'E-mail', 'Téléphone', 'Équipe', 'Sujet de thèse', 'Directeur de thèse', 'Co-directeur', 'Boursier',
        ], [[
            'RAKOTO', 'Jean Marie', 'M', '25/03/1990', 'Mahajanga', 'Malagasy', '401011012345', 'Lot II A 12 Mahajanga',
            'jean.rakoto@exemple.mg', '034 12 345 67', 'EAD 1', 'Projet de thèse', 'Pr RABE Hery', '', 'Non',
        ]]);
    }

    /**
     * Avec « simulation », rien n'est enregistré : la réponse liste les lignes valides et les erreurs.
     * Sinon, seules les lignes valides sont importées.
     */
    public function importer(Request $request, AnalyserImportDoctorants $analyser, CreerDoctorant $creerDoctorant): JsonResponse
    {
        $this->authorize('create', Doctorant::class);
        $request->validate(
            ['fichier' => ['required', 'file', 'mimes:csv,txt', 'max:2048'], 'simulation' => ['boolean'], 'envoyer_invitation' => ['boolean'], 'candidats' => ['boolean']],
            ['fichier.mimes' => 'Le fichier doit être au format CSV (Excel : Enregistrer sous, CSV UTF-8).', 'fichier.max' => 'Le fichier ne doit pas dépasser 2 Mo.'],
            ['fichier' => 'fichier'],
        );

        $candidats = $request->boolean('candidats');
        $analyse = $analyser($request->file('fichier')->getRealPath(), $candidats);

        if ($analyse['erreur_fichier']) {
            return response()->json(['message' => $analyse['erreur_fichier'], 'errors' => ['fichier' => [$analyse['erreur_fichier']]]], 422);
        }

        $valides = array_values(array_filter($analyse['lignes'], fn (array $ligne) => $ligne['erreurs'] === []));
        $enErreur = array_values(array_filter($analyse['lignes'], fn (array $ligne) => $ligne['erreurs'] !== []));
        $crees = 0;

        if (! $request->boolean('simulation')) {
            foreach ($valides as $ligne) {
                $creerDoctorant($ligne['donnees'], ! $candidats && $request->boolean('envoyer_invitation'));
                $crees++;
            }
        }

        return response()->json(['data' => [
            'simulation' => $request->boolean('simulation'),
            'total' => count($analyse['lignes']),
            'valides' => count($valides),
            'crees' => $crees,
            'apercu' => array_map(fn (array $ligne) => [
                'ligne' => $ligne['ligne'],
                'libelle' => $ligne['libelle'],
                'email' => $ligne['donnees']['email'] ?? null,
                'niveau' => $ligne['donnees']['inscription']['niveau'] ?? null,
            ], array_slice($valides, 0, 50)),
            'erreurs' => array_map(fn (array $ligne) => [
                'ligne' => $ligne['ligne'],
                'libelle' => $ligne['libelle'],
                'erreurs' => $ligne['erreurs'],
            ], $enErreur),
        ]]);
    }
}
