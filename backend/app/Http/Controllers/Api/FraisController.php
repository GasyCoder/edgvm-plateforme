<?php

namespace App\Http\Controllers\Api;

use App\Actions\AjouterFrais;
use App\Enums\StatutDoctorant;
use App\Enums\StatutInscription;
use App\Http\Controllers\Controller;
use App\Http\Requests\AjouterFraisRequest;
use App\Http\Resources\FraisDoctorantResource;
use App\Http\Resources\PaiementResource;
use App\Models\AnneeUniversitaire;
use App\Models\Doctorant;
use App\Models\FraisDoctorant;
use App\Models\Paiement;
use App\Models\TypeFrais;
use App\Support\SituationFinanciere;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Frais dus par les doctorants : situation d'un doctorant, application (un ou plusieurs), ajustement, annulation.
 */
class FraisController extends Controller
{
    /**
     * Situation financière d'un doctorant : totaux, frais et paiements.
     */
    public function finances(Request $request, Doctorant $doctorant): JsonResponse
    {
        $this->authorize('voirFinances', [Paiement::class, $doctorant]);
        $admin = $request->user()->estSuperAdmin();

        $frais = $doctorant->frais()->with('anneeUniversitaire')
            ->when(! $admin, fn (Builder $q) => $q->whereNull('annule_le'))
            ->orderByRaw('annule_le is not null')->orderByDesc('echeance')->get();
        $paiements = $doctorant->paiements()->with(['imputations.frais', 'saisiPar'])
            ->when(! $admin, fn (Builder $q) => $q->whereNull('annule_le'))
            ->latest('date_paiement')->latest('id')->get();

        return response()->json(['data' => [
            'situation' => SituationFinanciere::pour($doctorant),
            'frais' => FraisDoctorantResource::collection($frais),
            'paiements' => PaiementResource::collection($paiements),
        ]]);
    }

    public function store(AjouterFraisRequest $request, AjouterFrais $ajouter): JsonResponse
    {
        $type = $request->filled('type_frais_id') ? TypeFrais::query()->find($request->integer('type_frais_id')) : null;
        $annee = $request->filled('annee_universitaire_id') ? AnneeUniversitaire::query()->find($request->integer('annee_universitaire_id')) : AnneeUniversitaire::courante();

        $doctorants = $request->filled('doctorants')
            ? Doctorant::query()->whereIn('uuid', $request->input('doctorants'))->with('user')->orderBy('nom')->get()
            : $this->cible($request, $annee);

        $resultat = $ajouter($doctorants, $type, $request->input('libelle'), $request->filled('montant') ? $request->integer('montant') : null, $annee, $request->input('echeance'), $request->user());

        return response()->json(['data' => [
            'crees' => $resultat['crees']->count(),
            'total' => $resultat['crees']->sum('montant'),
            'ignores' => $resultat['ignores'],
        ]], 201);
    }

    /**
     * Ajuste le montant (bourse, remise) ou l'échéance d'un frais, avec un motif.
     */
    public function update(Request $request, FraisDoctorant $frais): FraisDoctorantResource
    {
        $this->authorize('create', Paiement::class);
        $request->validate([
            'montant' => ['required', 'integer', 'min:'.max(1, $frais->montant_paye), 'max:100000000'],
            'echeance' => ['nullable', 'date'],
            'motif_ajustement' => [$request->integer('montant') !== $frais->montant ? 'required' : 'nullable', 'nullable', 'string', 'max:300'],
        ], [
            'montant.min' => 'Le montant ne peut pas être inférieur à ce qui est déjà payé ('.number_format($frais->montant_paye, 0, ',', ' ').' Ar).',
            'motif_ajustement.required' => 'Indiquez le motif du changement de montant (bourse, remise...).',
        ], ['echeance' => 'échéance']);

        $frais->update($request->only(['montant', 'echeance', 'motif_ajustement']));

        return new FraisDoctorantResource($frais->load('anneeUniversitaire'));
    }

    /**
     * Annule un frais (exonération, erreur). Un frais déjà en partie payé ne s'annule pas : annulez d'abord le paiement.
     */
    public function annuler(Request $request, FraisDoctorant $frais): FraisDoctorantResource|JsonResponse
    {
        $this->authorize('create', Paiement::class);
        $request->validate(['motif' => ['required', 'string', 'min:3', 'max:300']], ['motif.required' => "Indiquez le motif de l'annulation (exonération...)."]);

        if ($frais->montant_paye > 0) {
            return response()->json(['message' => 'Ce frais est déjà en partie payé : annulez d\'abord le paiement, ou ajustez le montant.'], 409);
        }

        $frais->update(['annule_le' => now(), 'motif_annulation' => $request->string('motif')->trim()->toString()]);

        return new FraisDoctorantResource($frais->load('anneeUniversitaire'));
    }

    /**
     * @return Collection<int, Doctorant>
     */
    private function cible(Request $request, ?AnneeUniversitaire $annee)
    {
        return Doctorant::query()
            ->with('user')
            ->where('statut', StatutDoctorant::Actif->value)
            ->when($request->input('cible') === 'inscrits', fn (Builder $q) => $q->whereHas('inscriptions', function (Builder $inscription) use ($annee, $request): void {
                $inscription->where('annee_universitaire_id', $annee?->id)->where('statut', '!=', StatutInscription::Annulee->value);
                match ($request->input('niveau')) {
                    null, '' => null,
                    'derogation' => $inscription->where('est_derogation', true),
                    default => $inscription->where('est_derogation', false)->where('niveau', $request->input('niveau')),
                };
            }))
            ->when($request->integer('equipe'), fn (Builder $q, int $equipe) => $q->where('equipe_accueil_id', $equipe))
            ->orderBy('nom')
            ->get();
    }
}
