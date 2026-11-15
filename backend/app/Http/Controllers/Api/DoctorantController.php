<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreerDoctorant;
use App\Http\Controllers\Controller;
use App\Http\Requests\DoctorantRequest;
use App\Http\Resources\DoctorantResource;
use App\Models\Doctorant;
use App\Support\SituationFinanciere;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class DoctorantController extends Controller
{
    /**
     * Liste filtrable : recherche (nom, matricule, e-mail), équipe, niveau de l'année en cours, statut.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Doctorant::class);

        $doctorants = self::requeteFiltree($request)
            ->paginate(min(max($request->integer('par_page', 20), 5), 100))
            ->withQueryString();

        return DoctorantResource::collection($doctorants);
    }

    /**
     * Doctorants visibles par l'utilisateur, filtrés comme dans la liste (partagé avec l'export).
     *
     * @return Builder<Doctorant>
     */
    public static function requeteFiltree(Request $request, bool $avecFiltreNiveau = true): Builder
    {
        return SituationFinanciere::avecTotaux(Doctorant::query())
            ->visiblesPour($request->user())
            ->with(['user', 'equipeAccueil', 'inscriptionCourante.anneeUniversitaire'])
            ->recherche($request->string('recherche')->trim()->toString())
            ->when($request->integer('equipe'), fn ($requete, int $equipe) => $requete->where('equipe_accueil_id', $equipe))
            ->when($request->string('statut')->toString(), fn ($requete, string $statut) => $requete->where('statut', $statut))
            ->niveauCourant($avecFiltreNiveau ? ($request->string('niveau')->toString() ?: null) : null)
            ->when($request->filled('boursier'), fn ($requete) => $requete->where('est_boursier', $request->boolean('boursier')))
            ->when($request->boolean('sans_inscription'), fn ($requete) => $requete->whereDoesntHave('inscriptionCourante'))
            ->when($request->input('paiement'), fn (Builder $requete, string $situation) => PaiementController::filtrerSituation($requete, $situation))
            ->orderBy('nom')
            ->orderBy('prenoms');
    }

    /**
     * Compteurs des cartes de la liste : doctorants par niveau de l'année courante (D1, D2, D3, dérogation),
     * avec tous les filtres de la liste sauf le niveau, pour que les cartes restent comparables quand on en choisit une.
     *
     * @return array{total: int, niveaux: array{D1: int, D2: int, D3: int, derogation: int}, boursiers: int}
     */
    public function compteurs(Request $request): array
    {
        $this->authorize('viewAny', Doctorant::class);

        $base = self::requeteFiltree($request, avecFiltreNiveau: false);
        $compter = fn (callable $filtre): int => tap($base->clone(), $filtre)->count();

        return [
            'total' => $base->clone()->count(),
            'niveaux' => collect(['D1', 'D2', 'D3', 'derogation'])
                ->mapWithKeys(fn (string $niveau) => [$niveau => $compter(fn (Builder $q) => $q->niveauCourant($niveau))])
                ->all(),
            'boursiers' => $compter(fn (Builder $q) => $q->where('est_boursier', true)),
        ];
    }

    public function show(Doctorant $doctorant): DoctorantResource
    {
        $this->authorize('view', $doctorant);

        $doctorant = SituationFinanciere::avecTotaux(Doctorant::query()->whereKey($doctorant->id))->firstOrFail();

        return new DoctorantResource($doctorant->load(['user', 'equipeAccueil', 'inscriptions.anneeUniversitaire', 'inscriptions.frais', 'inscriptionCourante.anneeUniversitaire']));
    }

    /**
     * Crée le compte, le dossier et, si demandé, la première inscription ; envoie le lien de premier accès.
     * Avec « etape », vérifie seulement les champs d'une étape de l'assistant (rien n'est enregistré).
     */
    public function store(DoctorantRequest $request, CreerDoctorant $creerDoctorant): JsonResponse|Response
    {
        if ($request->estVerificationEtape()) {
            return response()->noContent();
        }

        $doctorant = $creerDoctorant($request->validated(), $request->boolean('envoyer_invitation', true));

        return $this->show($doctorant)->response()->setStatusCode(201);
    }

    public function update(DoctorantRequest $request, Doctorant $doctorant): DoctorantResource|Response
    {
        if ($request->estVerificationEtape()) {
            return response()->noContent();
        }

        DB::transaction(function () use ($request, $doctorant): void {
            $doctorant->update($request->safe()->except(['email', 'telephone', 'envoyer_invitation']));
            $doctorant->user->update([
                'name' => $doctorant->nomComplet(),
                'email' => $request->string('email')->toString(),
                'telephone' => $request->input('telephone'),
            ]);
        });

        return $this->show($doctorant->refresh());
    }
}
