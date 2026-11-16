<?php

namespace App\Http\Controllers\Api;

use App\Actions\AnnulerPaiement;
use App\Actions\EnregistrerPaiement;
use App\Enums\StatutDoctorant;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaiementRequest;
use App\Http\Resources\PaiementResource;
use App\Http\Resources\SituationDoctorantResource;
use App\Models\AnneeUniversitaire;
use App\Models\Doctorant;
use App\Models\FraisDoctorant;
use App\Models\Paiement;
use App\Notifications\RappelPaiement;
use App\Support\FichierCsv;
use App\Support\ReglagesActes;
use App\Support\SituationFinanciere;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Journal des paiements, encaissement, annulation, reçu PDF, situation des doctorants et chiffres clés.
 */
class PaiementController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Paiement::class);
        $requete = $this->journal($request);
        $total = (clone $requete)->whereNull('annule_le')->sum('montant');

        return PaiementResource::collection(
            $requete->with(['doctorant', 'saisiPar', 'imputations.frais'])->latest('date_paiement')->latest('id')->paginate(min(max($request->integer('par_page', 20), 5), 100))->withQueryString()
        )->additional(['total_encaisse' => (int) $total]);
    }

    public function store(PaiementRequest $request, Doctorant $doctorant, EnregistrerPaiement $enregistrer): JsonResponse
    {
        $paiement = $enregistrer($doctorant, $request->safe()->except('justificatif'), $request->user(), $request->file('justificatif'));

        return (new PaiementResource($paiement->load(['doctorant', 'saisiPar', 'imputations.frais'])))->response()->setStatusCode(201);
    }

    public function annuler(Request $request, Paiement $paiement, AnnulerPaiement $annuler): PaiementResource
    {
        $this->authorize('annuler', $paiement);
        $request->validate(['motif' => ['required', 'string', 'min:5', 'max:300']], ['motif.required' => "Indiquez le motif de l'annulation."], ['motif' => 'motif']);

        $annuler($paiement, $request->string('motif')->trim()->toString(), $request->user());

        return new PaiementResource($paiement->refresh()->load(['doctorant', 'saisiPar', 'imputations.frais']));
    }

    /**
     * Ajoute ou remplace le reçu de versement d'un paiement déjà enregistré.
     */
    public function justificatif(Request $request, Paiement $paiement): PaiementResource
    {
        $this->authorize('create', Paiement::class);
        $request->validate(
            ['justificatif' => ['required', 'file', 'mimes:'.implode(',', Paiement::EXTENSIONS_JUSTIFICATIF), 'max:10240']],
            ['justificatif.required' => 'Choisissez la photo ou le scan du reçu de versement.', 'justificatif.mimes' => 'Le reçu de versement doit être une photo (JPG, PNG, WEBP) ou un PDF.', 'justificatif.max' => 'Le reçu de versement ne doit pas dépasser 10 Mo.'],
        );

        $paiement->joindreJustificatif($request->file('justificatif'));

        return new PaiementResource($paiement->refresh()->load(['doctorant', 'saisiPar', 'imputations.frais']));
    }

    /**
     * Reçu de versement (photo ou PDF), ouvert dans le navigateur.
     */
    public function voirJustificatif(Paiement $paiement): StreamedResponse
    {
        $this->authorize('view', $paiement);
        abort_unless($paiement->justificatif_chemin && Storage::disk(Paiement::DISQUE)->exists($paiement->justificatif_chemin), 404, 'Aucun reçu de versement.');

        return Storage::disk(Paiement::DISQUE)->response($paiement->justificatif_chemin, $paiement->justificatif_nom, [
            'Content-Type' => $paiement->justificatif_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }

    /**
     * Situation de chaque doctorant : dû, payé, reste, statut. Filtres : situation, équipe, recherche.
     */
    public function situation(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Paiement::class);

        $ids = $this->requeteSituation($request)->pluck('doctorants.id');
        $frais = FraisDoctorant::query()->whereIn('doctorant_id', $ids)->whereNull('annule_le');
        $totaux = [
            'du' => (int) (clone $frais)->sum('montant'),
            'paye' => (int) (clone $frais)->sum('montant_paye'),
            'en_retard' => (int) FraisDoctorant::query()->whereIn('doctorant_id', $ids)->enRetard()->selectRaw('coalesce(sum(montant - montant_paye), 0) as reste')->value('reste'),
        ];

        $doctorants = $this->requeteSituation($request)
            ->with(['equipeAccueil', 'inscriptionCourante.anneeUniversitaire'])
            ->withMin(['frais as prochaine_echeance' => fn (Builder $f) => $f->impayes()], 'echeance')
            ->orderBy('nom')->orderBy('prenoms')
            ->paginate(min(max($request->integer('par_page', 20), 5), 100))->withQueryString();

        return SituationDoctorantResource::collection($doctorants)
            ->additional(['totaux' => [...$totaux, 'reste' => max(0, $totaux['du'] - $totaux['paye']), 'doctorants' => $ids->count()]]);
    }

    /**
     * Situation filtrée (ou sélection « ids ») en CSV pour Excel.
     */
    public function exporterSituation(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Paiement::class);
        $doctorants = $this->requeteSituation($request)->with(['equipeAccueil', 'inscriptionCourante.anneeUniversitaire', 'user'])
            ->withMin(['frais as prochaine_echeance' => fn (Builder $f) => $f->impayes()], 'echeance')
            ->orderBy('nom')->orderBy('prenoms')->get();

        return FichierCsv::telecharger('situation-paiements-'.now()->format('Y-m-d').'.csv',
            ['Matricule', 'Doctorant', 'Équipe', 'Niveau', 'E-mail', 'Téléphone', 'Dû (Ar)', 'Payé (Ar)', 'Reste (Ar)', 'En retard (Ar)', 'Prochaine échéance', 'Situation'],
            $doctorants->map(function (Doctorant $d): array {
                $s = SituationFinanciere::pour($d);
                $echeance = $d->getAttribute('prochaine_echeance');

                return [$d->matricule, $d->nomComplet(), $d->equipeAccueil?->sigle, $d->inscriptionCourante?->niveauAffiche(), $d->user?->email, $d->user?->telephone,
                    $s['du'], $s['paye'], $s['reste'], $s['en_retard'], $echeance ? date('d/m/Y', strtotime($echeance)) : '', $s['statut_libelle']];
            }),
        );
    }

    /**
     * Rappel de paiement à des doctorants choisis (plateforme et e-mail). Ignorés : rien à payer, compte inactif,
     * ou rappel déjà envoyé dans les dernières 24 heures.
     */
    public function rappeler(Request $request): JsonResponse
    {
        $this->authorize('create', Paiement::class);
        $request->validate(['doctorants' => ['required', 'array', 'min:1', 'max:500'], 'doctorants.*' => ['string', 'exists:doctorants,uuid']]);

        $envoyes = 0;
        $ignores = 0;
        $doctorants = SituationFinanciere::avecTotaux(Doctorant::query()->whereIn('uuid', $request->input('doctorants'))->with('user'))
            ->withMin(['frais as prochaine_echeance' => fn (Builder $f) => $f->impayes()], 'echeance')->get();

        foreach ($doctorants as $doctorant) {
            $situation = SituationFinanciere::pour($doctorant);
            $user = $doctorant->user;

            if ($situation['reste'] === 0 || ! $user?->actif || $user->notifications()->where('type', RappelPaiement::class)->where('created_at', '>=', now()->subDay())->exists()) {
                $ignores++;

                continue;
            }

            $echeance = $doctorant->getAttribute('prochaine_echeance');
            $user->notify(new RappelPaiement($situation['en_retard'], $situation['reste'], $echeance ? Carbon::parse($echeance) : null));
            $envoyes++;
        }

        return response()->json(['data' => ['envoyes' => $envoyes, 'ignores' => $ignores]]);
    }

    /**
     * Doctorants de la situation : filtres de la page, ou sélection « ids ».
     *
     * @return Builder<Doctorant>
     */
    private function requeteSituation(Request $request): Builder
    {
        return SituationFinanciere::avecTotaux(Doctorant::query()->where('statut', '!=', StatutDoctorant::Candidat->value))
            ->when($request->filled('ids'), fn (Builder $q) => $q->whereIn('uuid', (array) $request->input('ids')))
            ->recherche($request->string('recherche')->trim()->toString())
            ->when($request->integer('equipe'), fn (Builder $q, int $equipe) => $q->where('equipe_accueil_id', $equipe))
            ->when($request->input('situation'), fn (Builder $q, string $situation) => self::filtrerSituation($q, $situation));
    }

    /**
     * Chiffres clés : encaissé (année en cours, mois), reste à recouvrer, retards.
     */
    public function statistiques(): JsonResponse
    {
        $this->authorize('viewAny', Paiement::class);
        $annee = AnneeUniversitaire::courante();
        $valides = fn () => Paiement::query()->whereNull('annule_le');
        $retards = FraisDoctorant::query()->enRetard();

        return response()->json(['data' => [
            'annee' => $annee?->libelle,
            'encaisse_annee' => (int) ($annee ? $valides()->whereBetween('date_paiement', [$annee->debut, $annee->fin])->sum('montant') : 0),
            'encaisse_mois' => (int) $valides()->whereBetween('date_paiement', [now()->startOfMonth(), now()->endOfMonth()])->sum('montant'),
            'reste_a_recouvrer' => (int) FraisDoctorant::query()->impayes()->selectRaw('coalesce(sum(montant - montant_paye), 0) as reste')->value('reste'),
            'montant_en_retard' => (int) (clone $retards)->selectRaw('coalesce(sum(montant - montant_paye), 0) as reste')->value('reste'),
            'doctorants_en_retard' => Doctorant::query()->enRetardDePaiement()->count(),
        ]]);
    }

    /**
     * Reçu PDF (téléchargement par lien, session classique).
     */
    public function recu(Paiement $paiement): Response
    {
        $this->authorize('view', $paiement);

        return $this->pdfRecus(new EloquentCollection([$paiement]), $paiement->numero_recu.'.pdf');
    }

    /**
     * Plusieurs reçus dans un seul PDF (une page par reçu), pour l'impression d'une sélection.
     */
    public function recus(Request $request): Response
    {
        $this->authorize('viewAny', Paiement::class);
        $paiements = Paiement::query()->whereIn('id', array_slice(array_map('intval', (array) $request->input('ids', [])), 0, 300))->orderBy('numero_recu')->get();
        abort_if($paiements->isEmpty(), 404, 'Aucun reçu à imprimer.');

        return $this->pdfRecus($paiements, 'recus-edgvm-'.now()->format('Y-m-d-His').'.pdf');
    }

    /**
     * @param  Collection<int, Paiement>  $paiements
     */
    private function pdfRecus($paiements, string $nom): Response
    {
        @set_time_limit(300);
        $paiements->load(['doctorant.equipeAccueil', 'imputations.frais.anneeUniversitaire', 'saisiPar']);

        $html = view('paiements.recu', [
            'recus' => $paiements->map(fn (Paiement $paiement) => [
                'paiement' => $paiement,
                'en_lettres' => SituationFinanciere::enLettres($paiement->montant),
                'reste' => $paiement->reste_apres ?? SituationFinanciere::pour($paiement->doctorant)['reste'],
            ]),
            'logo_gauche' => ReglagesActes::dataUri(ReglagesActes::image('logo_gauche'), 'logo_gauche'),
            'logo_droite' => ReglagesActes::dataUri(ReglagesActes::image('logo_droite'), 'logo_droite'),
            'ville' => ReglagesActes::texte('ville'),
        ])->render();

        return response(Pdf::loadHTML($html)->setPaper('a4')->setOption(['isRemoteEnabled' => false, 'isPhpEnabled' => false])->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nom.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Journal filtré en CSV (Excel).
     */
    public function exporter(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Paiement::class);
        $paiements = $this->journal($request)->with(['doctorant', 'saisiPar', 'imputations.frais'])->orderBy('date_paiement')->orderBy('id')->get();

        return FichierCsv::telecharger('paiements-edgvm-'.now()->format('Y-m-d').'.csv',
            ['Date', 'Reçu', 'Matricule', 'Doctorant', 'Montant (Ar)', 'Mode', 'Référence', 'Reçu de versement', 'Frais réglés', 'Saisi par', 'Statut'],
            $paiements->map(fn (Paiement $p) => [
                $p->date_paiement->format('d/m/Y'), $p->numero_recu, $p->doctorant->matricule, $p->doctorant->nomComplet(), $p->montant, $p->mode->libelle(), $p->reference, $p->justificatif_chemin ? 'Joint' : 'Manquant',
                $p->imputations->map(fn ($i) => $i->frais?->libelle.' ('.$i->montant.')')->implode(', '), $p->saisiPar?->name, $p->estAnnule() ? 'Annulé' : 'Valide',
            ]),
        );
    }

    /**
     * Filtre suivant la situation affichée : à jour (tout soldé), partiel (une partie versée, rien d'échu),
     * à payer (rien versé, rien d'échu), en retard (une échéance passée), rien (aucun frais).
     *
     * @param  Builder<Doctorant>  $query
     */
    public static function filtrerSituation(Builder $query, string $situation): void
    {
        match ($situation) {
            'en_retard' => $query->enRetardDePaiement(),
            'partiel' => $query->whereHas('frais', fn (Builder $f) => $f->impayes())->whereDoesntHave('frais', fn (Builder $f) => $f->enRetard())
                ->whereHas('frais', fn (Builder $f) => $f->whereNull('annule_le')->where('montant_paye', '>', 0)),
            'a_payer' => $query->whereHas('frais', fn (Builder $f) => $f->impayes())->whereDoesntHave('frais', fn (Builder $f) => $f->enRetard())
                ->whereDoesntHave('frais', fn (Builder $f) => $f->whereNull('annule_le')->where('montant_paye', '>', 0)),
            'a_jour' => $query->whereHas('frais', fn (Builder $f) => $f->whereNull('annule_le'))->whereDoesntHave('frais', fn (Builder $f) => $f->impayes()),
            'rien' => $query->whereDoesntHave('frais', fn (Builder $f) => $f->whereNull('annule_le')),
            default => null,
        };
    }

    /**
     * @return Builder<Paiement>
     */
    private function journal(Request $request): Builder
    {
        $terme = $request->string('recherche')->trim()->toString();

        return Paiement::query()
            ->when($request->filled('ids'), fn (Builder $q) => $q->whereIn('id', array_map('intval', (array) $request->input('ids'))))
            ->when($request->filled('du'), fn (Builder $q) => $q->whereDate('date_paiement', '>=', $request->input('du')))
            ->when($request->filled('au'), fn (Builder $q) => $q->whereDate('date_paiement', '<=', $request->input('au')))
            ->when($request->filled('mode'), fn (Builder $q) => $q->where('mode', $request->input('mode')))
            ->when($request->input('justificatif') === 'manquant', fn (Builder $q) => $q->whereNull('justificatif_chemin'))
            ->when($request->input('statut') === 'annule', fn (Builder $q) => $q->whereNotNull('annule_le'), fn (Builder $q) => $request->input('statut') === 'tous' ? $q : $q->whereNull('annule_le'))
            ->when($terme !== '', fn (Builder $q) => $q->where(fn (Builder $s) => $s->where('numero_recu', 'like', "%{$terme}%")->orWhere('reference', 'like', "%{$terme}%")->orWhereHas('doctorant', fn (Builder $d) => $d->recherche($terme))));
    }
}
