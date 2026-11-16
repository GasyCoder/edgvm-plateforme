<?php

namespace App\Http\Controllers\ApiPublique;

use App\Enums\StatutDoctorant;
use App\Http\Controllers\Controller;
use App\Models\Doctorant;
use App\Models\Inscription;
use App\Support\ReglagesApplication;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Banque de thèses du site vitrine, tirée des dossiers des doctorants :
 * « en cours » pour un doctorant actif, « soutenue » pour un docteur diplômé.
 * Seuls les doctorants qui ont accepté de figurer dans l'annuaire, avec un sujet renseigné.
 * Jamais publiés : matricule, contacts, date de naissance, paiements, dérogation, bourse, actes.
 */
class TheseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $terme = $request->string('recherche')->trim()->toString();
        $statut = $request->string('statut')->toString();

        $page = $this->requete()
            ->when($terme !== '', fn (Builder $q) => $q->where(fn (Builder $s) => $s
                ->where('sujet_these', 'like', "%{$terme}%")
                ->orWhere('nom', 'like', "%{$terme}%")
                ->orWhere('prenoms', 'like', "%{$terme}%")
                ->orWhere('directeur_these', 'like', "%{$terme}%")))
            ->when($statut === 'en_cours', fn (Builder $q) => $q->where('statut', StatutDoctorant::Actif->value))
            ->when($statut === 'soutenue', fn (Builder $q) => $q->where('statut', StatutDoctorant::Diplome->value))
            ->when($request->filled('equipe'), fn (Builder $q) => $q->whereHas('equipeAccueil', fn (Builder $e) => $e->where('sigle', $request->string('equipe')->trim()->toString())))
            ->orderByRaw('CASE statut WHEN ? THEN 0 ELSE 1 END', [StatutDoctorant::Diplome->value])
            ->orderBy('nom')
            ->orderBy('prenoms')
            ->paginate(min(max($request->integer('par_page', 20), 1), 50));

        return response()->json([
            'data' => collect($page->items())->map(fn (Doctorant $doctorant) => $this->format($doctorant))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $doctorant = $this->requete()->where('uuid', $id)->firstOrFail();

        return response()->json(['data' => $this->format($doctorant)]);
    }

    /**
     * @return Builder<Doctorant>
     */
    private function requete(): Builder
    {
        return Doctorant::query()
            ->whereIn('statut', [StatutDoctorant::Actif->value, StatutDoctorant::Diplome->value])
            ->where('visible_annuaire', true)
            ->whereNotNull('sujet_these')
            ->where('sujet_these', '!=', '')
            ->with(['equipeAccueil', 'inscriptions.anneeUniversitaire']);
    }

    /**
     * @return array<string, mixed>
     */
    private function format(Doctorant $doctorant): array
    {
        $annees = $doctorant->inscriptions
            ->filter(fn (Inscription $inscription) => $inscription->anneeUniversitaire !== null)
            ->sortBy(fn (Inscription $inscription) => $inscription->anneeUniversitaire->debut);
        $soutenue = $doctorant->statut === StatutDoctorant::Diplome;

        return [
            'id' => $doctorant->uuid,
            'titre' => $doctorant->sujet_these,
            'resume' => null,
            'statut' => $soutenue ? 'soutenue' : 'en_cours',
            'annee' => ($soutenue ? $annees->last() : $annees->first())?->anneeUniversitaire->debut?->year,
            'date_soutenance' => null,
            'mots_cles' => [],
            'doctorant' => ['nom' => $doctorant->nomComplet()],
            'directeur' => filled($doctorant->directeur_these) ? ['nom' => $doctorant->directeur_these, 'titre' => null] : null,
            'equipe' => $doctorant->equipeAccueil ? ['sigle' => $doctorant->equipeAccueil->sigle, 'nom' => $doctorant->equipeAccueil->nom] : null,
            'specialite' => null,
            'universite' => ReglagesApplication::texte('ecole.universite'),
            'pdf_url' => null,
            'jury' => [],
        ];
    }
}
