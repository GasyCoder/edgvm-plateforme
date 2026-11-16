<?php

namespace App\Http\Controllers\Api;

use App\Actions\AdmettreCandidat;
use App\Enums\DecisionCandidature;
use App\Enums\StatutDoctorant;
use App\Exceptions\AdmissionImpossible;
use App\Http\Controllers\Controller;
use App\Http\Resources\DoctorantResource;
use App\Models\Doctorant;
use App\Models\Passage;
use App\Support\FichierCsv;
use App\Support\FichierExcel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Candidats à l'École : liste par état (en attente, ajournés, non admis, admis), compteurs, admission.
 */
class CandidatController extends Controller
{
    private const ETATS = ['en_attente', 'ajourne', 'non_admis', 'admis_jury', 'admis'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('create', Doctorant::class);

        $candidats = $this->requete($request, $request->input('etat'))
            ->with(['user', 'equipeAccueil', 'passages.seance'])
            ->orderByDesc('candidature_le')->orderBy('nom')
            ->paginate(min(max($request->integer('par_page', 20), 5), 100))
            ->withQueryString();

        return DoctorantResource::collection($candidats);
    }

    /**
     * @return array<string, int>
     */
    public function compteurs(Request $request): array
    {
        $this->authorize('create', Doctorant::class);

        return collect(['tous' => null, ...array_combine(self::ETATS, self::ETATS)])
            ->map(fn (?string $etat) => $this->requete($request, $etat ?? 'tous')->count())
            ->all();
    }

    /**
     * Export Excel ou CSV des candidats, avec les filtres de la liste (état, EAD, année, recherche).
     */
    public function exporter(Request $request, string $format): StreamedResponse
    {
        $this->authorize('create', Doctorant::class);

        $candidats = $this->requete($request, $request->input('etat'))
            ->with(['user', 'equipeAccueil', 'passages.seance'])
            ->when($request->filled('ids'), fn (Builder $q) => $q->whereIn('uuid', (array) $request->input('ids')))
            ->orderByDesc('candidature_le')->orderBy('nom')
            ->get();

        $entetes = ['Numéro', 'Nom', 'Prénoms', 'Genre', 'Date de naissance', 'Lieu de naissance', 'Nationalité', 'E-mail', 'Téléphone',
            'EAD visée', 'Projet de thèse', 'Directeur pressenti', 'Boursier', 'Candidature déposée le', 'État', 'Dernière présentation', 'Observation du jury'];

        $lignes = $candidats->map(function (Doctorant $candidat): array {
            $derniere = $candidat->passages->filter(fn (Passage $p) => $p->seance?->pourCandidats())->sortByDesc('debut')->first();

            return [
                $candidat->matricule,
                mb_strtoupper($candidat->nom),
                $candidat->prenoms,
                $candidat->genre,
                $candidat->date_naissance?->format('d/m/Y'),
                $candidat->lieu_naissance,
                $candidat->nationalite,
                $candidat->user->email,
                $candidat->user->telephone,
                $candidat->equipeAccueil?->sigle,
                $candidat->sujet_these,
                $candidat->directeur_these,
                $candidat->est_boursier ? 'Oui' : 'Non',
                $candidat->candidature_le?->format('d/m/Y'),
                $candidat->admis_le ? 'Devenu doctorant le '.$candidat->admis_le->format('d/m/Y') : ($candidat->decision_candidature?->libelle() ?? 'En attente'),
                $derniere?->debut->format('d/m/Y H:i'),
                $derniere?->decision_observation,
            ];
        });

        $nom = 'candidats-edgvm-'.now()->format('Y-m-d');

        return $format === 'xlsx'
            ? FichierExcel::telecharger($nom.'.xlsx', $entetes, $lignes, 'Candidats')
            : FichierCsv::telecharger($nom.'.csv', $entetes, $lignes);
    }

    /**
     * Admission depuis la fiche du candidat (décision « admis » déjà enregistrée par le jury).
     */
    public function admettre(Request $request, Doctorant $doctorant, AdmettreCandidat $admettre): JsonResponse
    {
        $this->authorize('update', $doctorant);

        try {
            $resultat = $admettre($doctorant, $request->user());
        } catch (AdmissionImpossible $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => "{$resultat['doctorant']->nomComplet()} est admis : matricule {$resultat['doctorant']->matricule}, inscrit en D1"
                .($resultat['acte'] ? ", attestation n° {$resultat['acte']->numero} délivrée" : '').'.',
            'acte' => $resultat['acte'] ? ['id' => $resultat['acte']->id, 'numero' => $resultat['acte']->numero] : null,
        ]);
    }

    /**
     * États : en_attente (pas encore de décision), ajourne, non_admis, admis_jury (admis mais pas encore inscrit),
     * admis (devenus doctorants), tous (candidats en cours, sans les admis).
     *
     * @return Builder<Doctorant>
     */
    private function requete(Request $request, ?string $etat): Builder
    {
        $candidat = fn (Builder $q) => $q->where('statut', StatutDoctorant::Candidat->value);

        return Doctorant::query()
            ->recherche($request->string('recherche')->trim()->toString())
            ->when($request->integer('equipe'), fn (Builder $q, int $equipe) => $q->where('equipe_accueil_id', $equipe))
            ->when($request->integer('annee'), fn (Builder $q, int $annee) => $q->whereYear('candidature_le', $annee))
            ->where(fn (Builder $q) => match ($etat) {
                'en_attente' => $candidat($q)->whereNull('decision_candidature'),
                'ajourne' => $candidat($q)->where('decision_candidature', DecisionCandidature::Ajourne->value),
                'non_admis' => $candidat($q)->where('decision_candidature', DecisionCandidature::NonAdmis->value),
                'admis_jury' => $candidat($q)->where('decision_candidature', DecisionCandidature::Admis->value),
                'admis' => $q->whereNotNull('admis_le'),
                default => $candidat($q),
            });
    }
}
