<?php

namespace App\Http\Controllers\Api;

use App\Actions\EnvoyerInvitation;
use App\Enums\RoleUtilisateur;
use App\Enums\StatutDoctorant;
use App\Enums\StatutInscription;
use App\Enums\TypeInscription;
use App\Http\Controllers\Controller;
use App\Http\Requests\DoctorantRequest;
use App\Http\Resources\DoctorantResource;
use App\Models\Doctorant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DoctorantController extends Controller
{
    /**
     * Liste filtrable : recherche (nom, matricule, e-mail), équipe, niveau de l'année en cours, statut.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Doctorant::class);

        $doctorants = Doctorant::query()
            ->visiblesPour($request->user())
            ->with(['user', 'equipeAccueil', 'inscriptionCourante.anneeUniversitaire'])
            ->recherche($request->string('recherche')->trim()->toString())
            ->when($request->integer('equipe'), fn ($requete, int $equipe) => $requete->where('equipe_accueil_id', $equipe))
            ->when($request->string('statut')->toString(), fn ($requete, string $statut) => $requete->where('statut', $statut))
            ->niveauCourant($request->string('niveau')->toString() ?: null)
            ->when($request->boolean('sans_inscription'), fn ($requete) => $requete->whereDoesntHave('inscriptionCourante'))
            ->orderBy('nom')
            ->orderBy('prenoms')
            ->paginate(min(max($request->integer('par_page', 20), 5), 100))
            ->withQueryString();

        return DoctorantResource::collection($doctorants);
    }

    public function show(Doctorant $doctorant): DoctorantResource
    {
        $this->authorize('view', $doctorant);

        return new DoctorantResource($doctorant->load(['user', 'equipeAccueil', 'inscriptions.anneeUniversitaire', 'inscriptionCourante.anneeUniversitaire']));
    }

    /**
     * Crée le compte, le dossier et, si demandé, la première inscription ; envoie le lien de premier accès.
     */
    public function store(DoctorantRequest $request, EnvoyerInvitation $envoyerInvitation): DoctorantResource
    {
        $doctorant = DB::transaction(function () use ($request): Doctorant {
            $user = User::query()->create([
                'name' => trim(mb_strtoupper($request->string('nom')).' '.$request->string('prenoms')),
                'email' => $request->string('email')->toString(),
                'telephone' => $request->input('telephone'),
                'password' => Str::password(32),
                'role' => RoleUtilisateur::Doctorant,
                'actif' => true,
            ]);

            $doctorant = $user->doctorant()->create([
                ...$request->safe()->except(['email', 'telephone', 'inscription', 'envoyer_invitation', 'matricule', 'statut']),
                'matricule' => $request->filled('matricule') ? $request->string('matricule')->toString() : Doctorant::prochainMatricule(),
                'statut' => $request->input('statut', StatutDoctorant::Actif->value),
            ]);

            if ($request->filled('inscription')) {
                $doctorant->inscriptions()->create([
                    ...$request->validated('inscription'),
                    'type' => TypeInscription::Premiere,
                    'statut' => StatutInscription::Validee,
                    'date_inscription' => now()->toDateString(),
                ]);
            }

            return $doctorant;
        });

        if ($request->boolean('envoyer_invitation', true)) {
            $envoyerInvitation($doctorant->user);
        }

        return $this->show($doctorant);
    }

    public function update(DoctorantRequest $request, Doctorant $doctorant): DoctorantResource
    {
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
