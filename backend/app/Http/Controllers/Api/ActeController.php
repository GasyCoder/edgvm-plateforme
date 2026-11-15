<?php

namespace App\Http\Controllers\Api;

use App\Actions\DelivrerActe;
use App\Exceptions\ActeImpossible;
use App\Http\Controllers\Controller;
use App\Http\Requests\DelivrerActeRequest;
use App\Http\Resources\ActeResource;
use App\Models\Acte;
use App\Models\AnneeUniversitaire;
use App\Models\Doctorant;
use App\Support\GenerateurPdfActes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

/**
 * Registre des actes : délivrance un par un ou en lot, annulation, PDF.
 */
class ActeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $terme = $request->string('recherche')->trim()->toString();

        $actes = Acte::query()
            ->visiblesPour($request->user())
            ->with(['modele', 'doctorant', 'anneeUniversitaire', 'delivrePar'])
            ->when($request->filled('doctorant'), fn (Builder $requete) => $requete->whereHas('doctorant', fn (Builder $d) => $d->where('uuid', $request->input('doctorant'))))
            ->when($request->integer('modele'), fn (Builder $requete, int $modele) => $requete->where('modele_acte_id', $modele))
            ->when($request->integer('annee'), fn (Builder $requete, int $annee) => $requete->where('annee_universitaire_id', $annee))
            ->when($request->input('statut') === 'valide', fn (Builder $requete) => $requete->whereNull('annule_le'))
            ->when($request->input('statut') === 'annule', fn (Builder $requete) => $requete->whereNotNull('annule_le'))
            ->when($terme !== '', fn (Builder $requete) => $requete->where(fn (Builder $sous) => $sous
                ->where('numero', 'like', "%{$terme}%")
                ->orWhereHas('doctorant', fn (Builder $d) => $d->recherche($terme))))
            ->latest('delivre_le')
            ->latest('id')
            ->paginate(min(max($request->integer('par_page', 20), 5), 100))
            ->withQueryString();

        return ActeResource::collection($actes);
    }

    public function store(DelivrerActeRequest $request, DelivrerActe $delivrer): JsonResponse
    {
        $doctorant = Doctorant::query()->where('uuid', $request->input('doctorant'))->firstOrFail();
        $annee = $this->annee($request);

        if (! $request->filled('doublons') && ($deja = Acte::dejaDelivres($request->modele(), [$doctorant->id], $annee)->first())) {
            return response()->json([
                'message' => "{$doctorant->nomComplet()} a déjà reçu cet acte".($deja->anneeUniversitaire && $request->modele()->exige_inscription ? " pour {$deja->anneeUniversitaire->libelle}" : '')." (n° {$deja->numero} du {$deja->delivre_le->format('d/m/Y')}).",
                'code' => 'deja_delivre',
                'deja' => [$deja->resumeDoublon()],
            ], 409);
        }

        try {
            $acte = $delivrer($request->modele(), $doctorant, $annee, $request->validated('champs') ?? [], $request->user());
        } catch (ActeImpossible $exception) {
            return response()->json(['message' => $exception->getMessage(), 'errors' => ['doctorant' => [$exception->getMessage()]]], 422);
        }

        return (new ActeResource($acte->load(['modele', 'doctorant', 'anneeUniversitaire', 'delivrePar'])))->response()->setStatusCode(201);
    }

    /**
     * Délivrance en masse : chaque doctorant est traité à part ; ceux qui ne remplissent pas les conditions sont listés.
     */
    public function lot(DelivrerActeRequest $request, DelivrerActe $delivrer): JsonResponse
    {
        @set_time_limit(300);
        $modele = $request->modele();
        $annee = $this->annee($request);
        $champs = $request->validated('champs') ?? [];
        $delivres = collect();
        $erreurs = [];

        $doctorants = Doctorant::query()->whereIn('uuid', $request->validated('doctorants'))->orderBy('nom')->orderBy('prenoms')->get();
        $deja = Acte::dejaDelivres($modele, $doctorants->modelKeys(), $annee)->keyBy('doctorant_id');

        if ($deja->isNotEmpty() && ! $request->filled('doublons')) {
            return response()->json([
                'message' => $deja->count() === 1
                    ? '1 doctorant de la sélection a déjà reçu cet acte.'
                    : "{$deja->count()} doctorants de la sélection ont déjà reçu cet acte.",
                'code' => 'deja_delivre',
                'deja' => $deja->values()->map->resumeDoublon()->all(),
            ], 409);
        }

        foreach ($doctorants as $doctorant) {
            if ($request->validated('doublons') === 'ignorer' && ($acte = $deja->get($doctorant->id))) {
                $erreurs[] = ['doctorant' => $doctorant->nomComplet(), 'matricule' => $doctorant->matricule, 'message' => "Déjà délivré (n° {$acte->numero}), ignoré."];

                continue;
            }

            try {
                $delivres->push($delivrer($modele, $doctorant, $annee, $champs, $request->user()));
            } catch (ActeImpossible $exception) {
                $erreurs[] = ['doctorant' => $doctorant->nomComplet(), 'matricule' => $doctorant->matricule, 'message' => $exception->getMessage()];
            }
        }

        return response()->json(['data' => [
            'delivres' => ActeResource::collection($delivres->each->load(['modele', 'doctorant', 'anneeUniversitaire', 'delivrePar'])),
            'erreurs' => $erreurs,
            'url_pdf' => $delivres->isEmpty() ? null : '/telechargements/actes.pdf?'.http_build_query(['ids' => $delivres->pluck('id')->all()]),
        ]]);
    }

    public function annuler(Request $request, Acte $acte): ActeResource
    {
        $this->authorize('annuler', $acte);
        $request->validate(['motif' => ['required', 'string', 'min:5', 'max:500']], ['motif.required' => "Indiquez le motif de l'annulation."], ['motif' => 'motif']);

        $acte->update(['annule_le' => now(), 'motif_annulation' => $request->string('motif')->trim()->toString(), 'annule_par' => $request->user()->id]);

        return new ActeResource($acte->load(['modele', 'doctorant', 'anneeUniversitaire', 'delivrePar']));
    }

    /**
     * PDF d'un acte (téléchargement par lien, session classique).
     */
    public function pdf(Acte $acte): Response
    {
        $this->authorize('view', $acte);

        return $this->reponsePdf(collect([$acte]), str_replace('/', '-', $acte->numero).'.pdf');
    }

    /**
     * Plusieurs actes dans un seul PDF (impression d'un lot).
     */
    public function pdfLot(Request $request): Response
    {
        $ids = array_slice(array_map('intval', (array) $request->input('ids', [])), 0, 300);
        $actes = Acte::query()->visiblesPour($request->user())->whereIn('id', $ids)->orderBy('numero')->get();
        abort_if($actes->isEmpty(), 404, 'Aucun acte à imprimer.');

        return $this->reponsePdf($actes, 'actes-edgvm-'.now()->format('Y-m-d-His').'.pdf');
    }

    /**
     * @param  Collection<int, Acte>  $actes
     */
    private function reponsePdf($actes, string $nom): Response
    {
        return response(GenerateurPdfActes::generer($actes), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nom.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function annee(DelivrerActeRequest $request): ?AnneeUniversitaire
    {
        return $request->filled('annee_universitaire_id') ? AnneeUniversitaire::query()->find($request->integer('annee_universitaire_id')) : null;
    }
}
