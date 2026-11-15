<?php

namespace App\Http\Controllers\ApiPublique;

use App\Enums\StatutDoctorant;
use App\Http\Controllers\Controller;
use App\Models\EquipeAccueil;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * Équipes d'accueil pour le site vitrine, reliées à ses fiches par le sigle.
 * Seuls des effectifs sont publiés, jamais de donnée personnelle.
 */
class EquipeController extends Controller
{
    public function index(): JsonResponse
    {
        $equipes = $this->requete()->orderBy('sigle')->get();

        return response()->json([
            'data' => $equipes->map(fn (EquipeAccueil $equipe) => $this->format($equipe))->values(),
            'meta' => ['total' => $equipes->count()],
        ]);
    }

    public function show(string $sigle): JsonResponse
    {
        $equipe = $this->requete()->where('sigle', $sigle)->firstOrFail();

        return response()->json(['data' => $this->format($equipe)]);
    }

    /**
     * @return Builder<EquipeAccueil>
     */
    private function requete(): Builder
    {
        $actifs = fn (Builder $doctorant) => $doctorant->where('statut', StatutDoctorant::Actif->value);
        $niveau = fn (string $niveau) => fn (Builder $doctorant) => $actifs($doctorant)->niveauCourant($niveau);

        return EquipeAccueil::query()
            ->withCount([
                'doctorants as effectif' => $actifs,
                'doctorants as effectif_d1' => $niveau('D1'),
                'doctorants as effectif_d2' => $niveau('D2'),
                'doctorants as effectif_d3' => $niveau('D3'),
                'doctorants as dans_annuaire' => fn (Builder $doctorant) => $actifs($doctorant)->where('visible_annuaire', true),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function format(EquipeAccueil $equipe): array
    {
        return [
            'sigle' => $equipe->sigle,
            'nom' => $equipe->nom,
            'description' => $equipe->description,
            'effectifs' => [
                'total' => (int) $equipe->getAttribute('effectif'),
                'D1' => (int) $equipe->getAttribute('effectif_d1'),
                'D2' => (int) $equipe->getAttribute('effectif_d2'),
                'D3' => (int) $equipe->getAttribute('effectif_d3'),
            ],
            'doctorants_annuaire' => (int) $equipe->getAttribute('dans_annuaire'),
        ];
    }
}
