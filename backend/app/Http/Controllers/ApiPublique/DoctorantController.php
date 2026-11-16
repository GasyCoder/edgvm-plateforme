<?php

namespace App\Http\Controllers\ApiPublique;

use App\Enums\StatutDoctorant;
use App\Http\Controllers\Controller;
use App\Models\Doctorant;
use App\Models\Inscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Annuaire des doctorants pour le site vitrine : doctorants actifs qui ont accepté d'y figurer.
 * Jamais publiés : matricule, contacts, date de naissance, paiements, dérogation, bourse, actes.
 */
class DoctorantController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $terme = $request->string('recherche')->trim()->toString();
        $niveau = $request->string('niveau')->upper()->toString();

        $page = Doctorant::query()
            ->where('statut', StatutDoctorant::Actif->value)
            ->where('visible_annuaire', true)
            ->with(['equipeAccueil', 'inscriptionCourante', 'inscriptions.anneeUniversitaire'])
            ->when($terme !== '', fn (Builder $q) => $q->where(fn (Builder $s) => $s
                ->where('nom', 'like', "%{$terme}%")
                ->orWhere('prenoms', 'like', "%{$terme}%")
                ->orWhere('sujet_these', 'like', "%{$terme}%")))
            ->when($request->filled('equipe'), fn (Builder $q) => $q->whereHas('equipeAccueil', fn (Builder $e) => $e->where('sigle', $request->string('equipe')->trim()->toString())))
            ->when(in_array($niveau, ['D1', 'D2', 'D3'], true), fn (Builder $q) => $q->whereHas('inscriptionCourante', fn (Builder $i) => $i->where('niveau', $niveau)))
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

    /**
     * @return array<string, mixed>
     */
    private function format(Doctorant $doctorant): array
    {
        $premiere = $doctorant->inscriptions
            ->filter(fn (Inscription $inscription) => $inscription->anneeUniversitaire !== null)
            ->sortBy(fn (Inscription $inscription) => $inscription->anneeUniversitaire->debut)
            ->first();

        return [
            'id' => $doctorant->uuid,
            'nom' => $doctorant->nomComplet(),
            'niveau' => $doctorant->inscriptionCourante?->niveau->value,
            'annee_inscription' => $premiere?->anneeUniversitaire->debut?->year,
            'sujet' => $doctorant->sujet_these,
            'equipe' => $doctorant->equipeAccueil ? ['sigle' => $doctorant->equipeAccueil->sigle, 'nom' => $doctorant->equipeAccueil->nom] : null,
            'directeur' => filled($doctorant->directeur_these) ? ['nom' => $doctorant->directeur_these, 'titre' => null] : null,
            'these_id' => filled($doctorant->sujet_these) ? $doctorant->uuid : null,
            'photo_url' => null,
        ];
    }
}
