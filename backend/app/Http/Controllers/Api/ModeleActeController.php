<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ModeleActeRequest;
use App\Http\Resources\ModeleActeResource;
use App\Models\Acte;
use App\Models\Doctorant;
use App\Models\ModeleActe;
use App\Support\GabaritActe;
use App\Support\GenerateurPdfActes;
use App\Support\NettoyeurHtml;
use App\Support\ReglagesActes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Modèles d'actes modifiables dans l'interface, avec aperçu PDF avant enregistrement.
 */
class ModeleActeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ModeleActe::class);

        return ModeleActeResource::collection(ModeleActe::query()->withCount('actes')->orderBy('ordre')->orderBy('nom')->get())
            ->additional(['variables' => collect(GabaritActe::VARIABLES)->map(fn (array $v, string $cle) => ['cle' => $cle, 'libelle' => $v[0], 'exemple' => $v[1]])->values()]);
    }

    /**
     * Modèles proposés à la délivrance (actifs), visibles par l'administration.
     */
    public function disponibles(Request $request): AnonymousResourceCollection
    {
        $this->authorize('create', Acte::class);

        return ModeleActeResource::collection(ModeleActe::query()->where('actif', true)->orderBy('ordre')->orderBy('nom')->get());
    }

    public function show(ModeleActe $modele): ModeleActeResource
    {
        $this->authorize('update', $modele);

        return new ModeleActeResource($modele->loadCount('actes'));
    }

    public function store(ModeleActeRequest $request): JsonResponse
    {
        $modele = ModeleActe::query()->create([
            ...$request->validated(),
            'corps' => NettoyeurHtml::nettoyer($request->string('corps')),
            'code' => Str::slug($request->string('nom')).'-'.Str::lower(Str::random(4)),
            'ordre' => (int) ModeleActe::query()->max('ordre') + 1,
        ]);

        return (new ModeleActeResource($modele->loadCount('actes')))->response()->setStatusCode(201);
    }

    public function update(ModeleActeRequest $request, ModeleActe $modele): ModeleActeResource
    {
        $modele->update([...$request->validated(), 'corps' => NettoyeurHtml::nettoyer($request->string('corps'))]);

        return new ModeleActeResource($modele->refresh()->loadCount('actes'));
    }

    /**
     * Un modèle déjà utilisé se désactive au lieu d'être supprimé (les actes délivrés le citent).
     */
    public function destroy(ModeleActe $modele): Response|JsonResponse
    {
        $this->authorize('delete', $modele);

        if ($modele->actes()->exists()) {
            return response()->json(['message' => 'Des actes ont été délivrés avec ce modèle : désactivez-le plutôt que de le supprimer.'], 409);
        }

        $modele->delete();

        return response()->noContent();
    }

    /**
     * PDF d'aperçu du modèle en cours de modification (rien n'est enregistré), avec un doctorant réel ou des exemples.
     */
    public function apercu(ModeleActeRequest $request): Response
    {
        $ville = ReglagesActes::texte('ville');
        $champs = collect($request->validated('champs', []));
        $doctorant = $request->filled('doctorant') ? Doctorant::query()->where('uuid', $request->input('doctorant'))->first() : null;
        $modele = new ModeleActe($request->validated());

        $valeurs = $doctorant
            ? GabaritActe::valeurs($doctorant, null, null, $modele, $champs->mapWithKeys(fn (array $c) => [$c['cle'] => '['.$c['libelle'].']'])->all(), 'EDGVM/APERÇU', now(), $ville)
            : GabaritActe::exemples($champs->all(), $ville);

        $acte = new Acte([
            'numero' => 'EDGVM/APERÇU',
            'code_verification' => 'apercu',
            'titre' => GabaritActe::remplir($request->string('titre'), $valeurs, html: false),
            'contenu' => NettoyeurHtml::nettoyer(GabaritActe::remplir($request->string('corps'), $valeurs)),
            'signataire' => ReglagesActes::instantane($request->boolean('inclure_signature', true)),
            'delivre_le' => now(),
        ]);

        return response(GenerateurPdfActes::generer(collect([$acte]), apercu: true), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="apercu.pdf"',
            'Cache-Control' => 'no-store',
        ]);
    }
}
