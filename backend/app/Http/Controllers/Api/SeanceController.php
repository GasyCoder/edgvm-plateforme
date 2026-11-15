<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleIntervenant;
use App\Enums\StatutDoctorant;
use App\Enums\StatutSeance;
use App\Http\Controllers\Controller;
use App\Http\Requests\SeanceRequest;
use App\Http\Resources\SeanceResource;
use App\Models\Doctorant;
use App\Models\MembreEquipe;
use App\Models\Passage;
use App\Models\Seance;
use App\Models\User;
use App\Notifications\ConvocationIntervenant;
use App\Notifications\ConvocationPassage;
use App\Support\Disponibilites;
use App\Support\SituationFinanciere;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Séances de communication : programmation des passages, modérateurs et jury sans double réservation,
 * règle de paiement soldé, convocations par e-mail et dans la plateforme.
 */
class SeanceController extends Controller
{
    private const RELATIONS = ['passages.doctorant.equipeAccueil', 'passages.doctorant.inscriptionCourante', 'passages.intervenants.equipeAccueil'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Seance::class);
        $avenir = $request->input('periode', 'a_venir') === 'a_venir';

        $seances = Seance::query()
            ->visiblesPour($request->user())
            ->withCount('passages')
            ->when($request->filled('type'), fn (Builder $q) => $q->where('type', $request->input('type')))
            ->when($avenir, fn (Builder $q) => $q->whereDate('date', '>=', today()), fn (Builder $q) => $q->whereDate('date', '<', today()))
            ->orderBy('date', $avenir ? 'asc' : 'desc')
            ->orderBy('heure_debut')
            ->paginate(20);

        return SeanceResource::collection($seances);
    }

    public function store(SeanceRequest $request): JsonResponse
    {
        $seance = Seance::query()->create([
            ...$request->validated(),
            'pause' => $request->integer('pause'),
            'exige_paiement_solde' => $request->boolean('exige_paiement_solde'),
            'statut' => StatutSeance::Brouillon,
            'cree_par' => $request->user()->id,
        ]);

        return (new SeanceResource($seance->load(self::RELATIONS)))->response()->setStatusCode(201);
    }

    public function show(Seance $seance): SeanceResource
    {
        $this->authorize('view', $seance);

        return new SeanceResource($seance->load(self::RELATIONS));
    }

    /**
     * Un changement de date, d'heure de début, de durée ou de pause recalcule les horaires des passages.
     */
    public function update(SeanceRequest $request, Seance $seance): SeanceResource|JsonResponse
    {
        abort_unless($seance->estModifiable(), 409, 'Séance annulée.');
        $seance->fill([...$request->validated(), 'pause' => $request->integer('pause')]);
        $horaires = $seance->isDirty(['date', 'heure_debut', 'duree_passage', 'pause']);

        try {
            DB::transaction(function () use ($seance, $horaires): void {
                $seance->save();
                if ($horaires) {
                    $this->replanifier($seance->refresh());
                }
            });
        } catch (RuntimeException $exception) {
            return $this->refusConflits($exception);
        }

        return new SeanceResource($seance->load(self::RELATIONS));
    }

    /**
     * Supprimer une séance sans convocation ; sinon il faut l'annuler (les personnes ont été prévenues).
     */
    public function destroy(Seance $seance): Response|JsonResponse
    {
        $this->authorize('delete', $seance);

        if ($seance->convocations_envoyees_le) {
            return response()->json(['message' => 'Les convocations sont parties : annulez la séance plutôt que de la supprimer.'], 409);
        }

        $seance->delete();

        return response()->noContent();
    }

    public function annuler(Seance $seance): SeanceResource
    {
        $this->authorize('update', $seance);
        $seance->update(['statut' => StatutSeance::Annulee]);

        return new SeanceResource($seance->load(self::RELATIONS));
    }

    /**
     * Doctorants que l'on peut programmer : filtres par niveau, équipe, recherche ; chacun avec sa raison s'il ne peut pas.
     */
    public function candidats(Request $request, Seance $seance): JsonResponse
    {
        $this->authorize('update', $seance);
        $dejaProgrammes = $seance->passages()->pluck('doctorant_id')->all();

        $doctorants = SituationFinanciere::avecTotaux(Doctorant::query())
            ->with(['equipeAccueil', 'inscriptionCourante'])
            ->where('statut', StatutDoctorant::Actif->value)
            ->recherche($request->string('recherche')->trim()->toString())
            ->when($request->integer('equipe'), fn (Builder $q, int $equipe) => $q->where('equipe_accueil_id', $equipe))
            ->niveauCourant($request->string('niveau')->toString() ?: null)
            ->orderBy('nom')->orderBy('prenoms')
            ->limit(300)
            ->get();

        return response()->json(['data' => $doctorants->map(fn (Doctorant $doctorant) => [
            'id' => $doctorant->uuid,
            'nom_complet' => $doctorant->nomComplet(),
            'matricule' => $doctorant->matricule,
            'niveau' => $doctorant->inscriptionCourante?->niveauAffiche(),
            'equipe' => $doctorant->equipeAccueil?->sigle,
            'sujet' => $doctorant->sujet_these,
            'deja_programme' => in_array($doctorant->id, $dejaProgrammes, true),
            'raison' => $seance->raisonInadmissible($doctorant),
        ])->values()]);
    }

    /**
     * Programme des doctorants à la suite des passages existants. Ceux qui ne peuvent pas passer sont listés avec la raison.
     */
    public function ajouterPassages(Request $request, Seance $seance): JsonResponse
    {
        $this->authorize('update', $seance);
        abort_unless($seance->estModifiable(), 409, 'Séance annulée.');
        $request->validate(['doctorants' => ['required', 'array', 'min:1', 'max:200'], 'doctorants.*' => ['string', 'distinct']], ['doctorants.required' => 'Choisissez au moins un doctorant.']);

        $doctorants = SituationFinanciere::avecTotaux(Doctorant::query())->whereIn('uuid', $request->input('doctorants'))->orderBy('nom')->orderBy('prenoms')->get();
        $dernier = $seance->passages()->reorder('fin', 'desc')->first();
        $prochain = $dernier ? $dernier->fin->addMinutes($seance->pause) : $seance->commencement();
        $ordre = (int) $seance->passages()->max('ordre');
        $ajoutes = 0;
        $refuses = [];

        foreach ($doctorants as $doctorant) {
            if ($seance->passages()->where('doctorant_id', $doctorant->id)->exists()) {
                $refuses[] = ['doctorant' => $doctorant->nomComplet(), 'raison' => 'Déjà programmé dans cette séance.'];

                continue;
            }

            if ($raison = $seance->raisonInadmissible($doctorant)) {
                $refuses[] = ['doctorant' => $doctorant->nomComplet(), 'raison' => $raison];

                continue;
            }

            $fin = $prochain->addMinutes($seance->duree_passage);
            if (($occupe = Disponibilites::occupationsDoctorant($doctorant->id, $prochain, $fin))->isNotEmpty()) {
                $refuses[] = ['doctorant' => $doctorant->nomComplet(), 'raison' => 'Déjà pris à cette heure : '.Disponibilites::decrire($occupe->first()).'.'];

                continue;
            }

            $seance->passages()->create(['doctorant_id' => $doctorant->id, 'debut' => $prochain, 'fin' => $fin, 'ordre' => ++$ordre]);
            $prochain = $fin->addMinutes($seance->pause);
            $ajoutes++;
        }

        return response()->json([
            'data' => new SeanceResource($seance->refresh()->load(self::RELATIONS)),
            'ajoutes' => $ajoutes,
            'refuses' => $refuses,
            'message' => $ajoutes ? "{$ajoutes} doctorant(s) programmé(s)." : 'Aucun doctorant programmé.',
        ]);
    }

    /**
     * Modifie l'heure, la durée ou le sujet d'un passage ; refusé si le doctorant ou un intervenant est déjà pris.
     */
    public function modifierPassage(Request $request, Passage $passage): JsonResponse
    {
        $passage->loadMissing(['seance', 'doctorant', 'intervenants']);
        $seance = $passage->seance;
        $this->authorize('update', $seance);
        $donnees = $request->validate([
            'heure_debut' => ['required', 'date_format:H:i'],
            'duree' => ['required', 'integer', 'min:5', 'max:480'],
            'sujet' => ['nullable', 'string', 'max:500'],
        ], ['heure_debut.date_format' => 'Heure au format 09:30.']);

        $debut = CarbonImmutable::parse($seance->date->toDateString().' '.$donnees['heure_debut']);
        $fin = $debut->addMinutes((int) $donnees['duree']);
        $conflits = $this->conflitsPassage($passage, $debut, $fin);

        if ($conflits !== []) {
            return response()->json(['message' => 'Ce créneau est déjà pris.', 'conflits' => $conflits, 'errors' => ['heure_debut' => [$conflits[0]]]], 422);
        }

        $passage->update(['debut' => $debut, 'fin' => $fin, 'sujet' => $donnees['sujet'] ?? null]);

        return response()->json(['data' => new SeanceResource($seance->refresh()->load(self::RELATIONS)), 'message' => 'Passage modifié.']);
    }

    public function supprimerPassage(Passage $passage): JsonResponse
    {
        $seance = $passage->seance;
        $this->authorize('update', $seance);
        $passage->delete();

        return response()->json(['data' => new SeanceResource($seance->refresh()->load(self::RELATIONS)), 'message' => 'Passage retiré.']);
    }

    /**
     * Remet les passages bout à bout dans l'ordre donné (ou l'ordre actuel), depuis l'heure de début.
     */
    public function replanifierPassages(Request $request, Seance $seance): JsonResponse
    {
        $this->authorize('update', $seance);
        $request->validate(['ordre' => ['nullable', 'array'], 'ordre.*' => ['integer']]);

        try {
            DB::transaction(function () use ($request, $seance): void {
                foreach ($request->input('ordre', []) as $position => $id) {
                    $seance->passages()->whereKey($id)->update(['ordre' => $position + 1]);
                }
                $this->replanifier($seance);
            });
        } catch (RuntimeException $exception) {
            return $this->refusConflits($exception);
        }

        return response()->json(['data' => new SeanceResource($seance->refresh()->load(self::RELATIONS)), 'message' => 'Horaires recalculés.']);
    }

    /**
     * Personnes des équipes que l'on peut choisir comme modérateur ou jury, avec leurs indisponibilités
     * sur les créneaux des passages sélectionnés.
     */
    public function disponibilites(Request $request, Seance $seance): JsonResponse
    {
        $this->authorize('update', $seance);
        $request->validate(['passages' => ['required', 'array', 'min:1'], 'passages.*' => ['integer']]);
        $passages = $seance->passages()->with('intervenants')->whereIn('id', $request->input('passages'))->get();

        $membres = MembreEquipe::query()->with('equipeAccueil')
            ->when($request->integer('equipe'), fn (Builder $q, int $equipe) => $q->where('equipe_accueil_id', $equipe))
            ->orderByRaw("CASE fonction WHEN 'responsable' THEN 0 WHEN 'encadrant' THEN 1 ELSE 2 END")
            ->orderBy('nom')
            ->get();

        return response()->json(['data' => $membres->map(function (MembreEquipe $membre) use ($passages): array {
            $occupations = $passages->flatMap(fn (Passage $passage) => Disponibilites::occupationsMembre($membre->id, $passage->debut, $passage->fin, $passages->modelKeys()))
                ->unique('id')->map(fn (Passage $occupe) => Disponibilites::decrire($occupe))->values();

            return [
                'id' => $membre->id,
                'nom_avec_titre' => $membre->nomAvecTitre(),
                'fonction_libelle' => $membre->fonction->libelle(),
                'equipe' => $membre->equipeAccueil?->sigle,
                'email' => $membre->email,
                'deja_affecte' => $passages->filter(fn (Passage $p) => $p->intervenants->contains('id', $membre->id))->count(),
                'occupations' => $occupations,
            ];
        })->values()]);
    }

    /**
     * Affecte des personnes (modérateur ou jury) à un ou plusieurs passages. Tout est refusé si l'une d'elles est déjà prise.
     */
    public function affecter(Request $request, Seance $seance): JsonResponse
    {
        $this->authorize('update', $seance);
        abort_unless($seance->estModifiable(), 409, 'Séance annulée.');
        $donnees = $request->validate([
            'passages' => ['required', 'array', 'min:1'],
            'passages.*' => ['integer', Rule::exists('passages', 'id')->where('seance_id', $seance->id)],
            'membres' => ['required', 'array', 'min:1', 'max:20'],
            'membres.*' => ['integer', 'distinct', Rule::exists('membres_equipe', 'id')],
            'role' => ['required', Rule::enum(RoleIntervenant::class)],
        ], ['membres.required' => 'Choisissez au moins une personne.', 'passages.required' => 'Choisissez au moins un passage.']);

        $passages = $seance->passages()->whereIn('id', $donnees['passages'])->get();
        $membres = MembreEquipe::query()->whereIn('id', $donnees['membres'])->get();
        $conflits = [];

        foreach ($membres as $membre) {
            foreach ($passages as $passage) {
                foreach (Disponibilites::occupationsMembre($membre->id, $passage->debut, $passage->fin, $passages->modelKeys()) as $occupe) {
                    $conflits[] = $membre->nomAvecTitre().' est déjà pris : '.Disponibilites::decrire($occupe).'.';
                }
            }
            /* Deux passages choisis qui se chevauchent : la même personne ne peut pas tenir les deux. */
            foreach ($passages as $a) {
                foreach ($passages as $b) {
                    if ($a->id < $b->id && $a->debut < $b->fin && $a->fin > $b->debut) {
                        $conflits[] = $membre->nomAvecTitre().' ne peut pas être à deux passages en même temps ('.$a->debut->format('H:i').' et '.$b->debut->format('H:i').').';
                    }
                }
            }
        }

        if ($conflits !== []) {
            $conflits = array_values(array_unique($conflits));

            return response()->json(['message' => 'Personne non disponible sur ce créneau.', 'conflits' => $conflits, 'errors' => ['membres' => [$conflits[0]]]], 422);
        }

        foreach ($passages as $passage) {
            $passage->intervenants()->syncWithoutDetaching($membres->mapWithKeys(fn (MembreEquipe $m) => [$m->id => ['role' => $donnees['role']]])->all());
            $membres->each(fn (MembreEquipe $m) => $passage->intervenants()->updateExistingPivot($m->id, ['role' => $donnees['role']]));
        }

        return response()->json([
            'data' => new SeanceResource($seance->refresh()->load(self::RELATIONS)),
            'message' => $membres->count().' personne(s) affectée(s) à '.$passages->count().' passage(s).',
        ]);
    }

    public function retirerIntervenant(Passage $passage, MembreEquipe $membre): JsonResponse
    {
        $seance = $passage->seance;
        $this->authorize('update', $seance);
        $passage->intervenants()->detach($membre->id);

        return response()->json(['data' => new SeanceResource($seance->refresh()->load(self::RELATIONS)), 'message' => 'Retiré du passage.']);
    }

    /**
     * Envoie les convocations, au choix :
     *  - « intelligent » (par défaut) : passages jamais convoqués, ou modifiés depuis l'envoi (horaire, lieu, sujet, modérateurs, jury) ;
     *  - « tous » : tous les passages ;
     *  - « passages » : seulement ceux de la liste (un par un ou une sélection).
     * Chaque doctorant concerné reçoit sa convocation (cloche et e-mail). Chaque modérateur ou jury des passages concernés
     * reçoit la liste complète de ses passages dans la séance (e-mail, et cloche s'il a un compte avec la même adresse).
     */
    public function convoquer(Request $request, Seance $seance): JsonResponse
    {
        $this->authorize('update', $seance);
        abort_unless($seance->estModifiable(), 409, 'Séance annulée.');
        $donnees = $request->validate([
            'mode' => ['nullable', Rule::in(['intelligent', 'tous', 'passages'])],
            'passages' => ['required_if:mode,passages', 'array'],
            'passages.*' => ['integer'],
        ]);
        $mode = $donnees['mode'] ?? 'intelligent';

        $seance->load(['passages.doctorant.user', 'passages.doctorant.inscriptionCourante', 'passages.intervenants']);
        $seance->passages->each->setRelation('seance', $seance);
        $passages = match ($mode) {
            'tous' => $seance->passages,
            'passages' => $seance->passages->whereIn('id', $donnees['passages'] ?? [])->values(),
            default => $seance->passages->filter(fn (Passage $p) => $p->etatConvocation() !== 'a_jour')->values(),
        };

        if ($passages->isEmpty()) {
            return response()->json(['message' => $mode === 'intelligent' ? 'Tout le monde a déjà reçu une convocation à jour.' : 'Aucun passage à convoquer.'], 422);
        }

        foreach ($passages as $passage) {
            $passage->doctorant->user?->notify(new ConvocationPassage($passage));
        }

        $sansEmail = [];
        $intervenants = 0;
        $passages->flatMap(fn (Passage $p) => $p->intervenants)->unique('id')->each(function (MembreEquipe $membre) use ($seance, &$sansEmail, &$intervenants): void {
            if (blank($membre->email)) {
                $sansEmail[] = $membre->nomAvecTitre();

                return;
            }

            $siens = $seance->passages->filter(fn (Passage $p) => $p->intervenants->contains('id', $membre->id))->values();
            $notification = new ConvocationIntervenant($seance, $membre, $siens);
            ($compte = User::query()->where('email', $membre->email)->first())
                ? $compte->notify($notification)
                : Notification::route('mail', $membre->email)->notify($notification);
            $intervenants++;
        });

        foreach ($passages as $passage) {
            $passage->forceFill(['convoque_le' => now(), 'convocation_empreinte' => $passage->empreinteConvocation()])->save();
        }
        $seance->update(['statut' => StatutSeance::Publiee, 'convocations_envoyees_le' => now()]);

        return response()->json([
            'data' => new SeanceResource($seance->refresh()->load(self::RELATIONS)),
            'message' => "Convocation envoyée à {$passages->count()} doctorant(s) et {$intervenants} modérateur(s) ou membre(s) du jury.",
            'envoyes' => $passages->count(),
            'sans_email' => $sansEmail,
        ]);
    }

    /**
     * Remet les passages bout à bout depuis le début de la séance. Lève une exception listant les conflits.
     */
    private function replanifier(Seance $seance): void
    {
        $passages = $seance->passages()->with(['doctorant', 'intervenants'])->reorder('ordre')->orderBy('debut')->get();
        $prochain = $seance->commencement();
        $conflits = [];

        foreach ($passages as $passage) {
            $fin = $prochain->addMinutes($seance->duree_passage);
            $conflits = [...$conflits, ...$this->conflitsPassage($passage, $prochain, $fin, $passages->modelKeys())];
            $passage->update(['debut' => $prochain, 'fin' => $fin]);
            $prochain = $fin->addMinutes($seance->pause);
        }

        if ($conflits !== []) {
            throw new RuntimeException(json_encode(array_values(array_unique($conflits)), JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * Conflits pour le doctorant et les intervenants d'un passage placé sur [début, fin[.
     *
     * @param  array<int, int>  $sauf  passages de la même séance à ignorer (en cours de replanification)
     * @return array<int, string>
     */
    private function conflitsPassage(Passage $passage, CarbonImmutable $debut, CarbonImmutable $fin, array $sauf = []): array
    {
        $sauf = [...$sauf, $passage->id];
        $conflits = Disponibilites::occupationsDoctorant($passage->doctorant_id, $debut, $fin, $sauf)
            ->map(fn (Passage $occupe) => $passage->doctorant->nomComplet().' est déjà pris : '.Disponibilites::decrire($occupe).'.')->all();

        foreach ($passage->intervenants as $membre) {
            foreach (Disponibilites::occupationsMembre($membre->id, $debut, $fin, $sauf) as $occupe) {
                $conflits[] = $membre->nomAvecTitre().' est déjà pris : '.Disponibilites::decrire($occupe).'.';
            }
        }

        return $conflits;
    }

    private function refusConflits(RuntimeException $exception): JsonResponse
    {
        $conflits = json_decode($exception->getMessage(), true) ?: [$exception->getMessage()];

        return response()->json(['message' => 'Les nouveaux horaires créent des conflits : rien n\'a été changé.', 'conflits' => $conflits], 422);
    }
}
