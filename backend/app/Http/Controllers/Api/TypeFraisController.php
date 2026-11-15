<?php

namespace App\Http\Controllers\Api;

use App\Actions\AppliquerNouveauTarif;
use App\Http\Controllers\Controller;
use App\Http\Requests\TypeFraisRequest;
use App\Http\Resources\TypeFraisResource;
use App\Models\Paiement;
use App\Models\TypeFrais;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Paramètres : catalogue des frais et tarifs.
 */
class TypeFraisController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('create', Paiement::class);

        return TypeFraisResource::collection(
            TypeFrais::query()->withCount(['frais', 'frais as frais_non_soldes_count' => fn ($q) => $q->impayes()->whereNull('motif_ajustement')])
                ->when($request->boolean('actifs'), fn ($q) => $q->where('actif', true))->orderBy('ordre')->orderBy('libelle')->get()
        );
    }

    public function store(TypeFraisRequest $request): JsonResponse
    {
        $type = TypeFrais::query()->create([
            ...$request->donnees(),
            'code' => Str::slug($request->string('libelle')).'-'.Str::lower(Str::random(4)),
            'ordre' => (int) TypeFrais::query()->max('ordre') + 1,
        ]);

        return (new TypeFraisResource($this->avecComptes($type)))->response()->setStatusCode(201);
    }

    private function avecComptes(TypeFrais $type): TypeFrais
    {
        return $type->loadCount(['frais', 'frais as frais_non_soldes_count' => fn ($q) => $q->impayes()->whereNull('motif_ajustement')]);
    }

    /**
     * Nouveau tarif. Avec « appliquer_aux_frais », les frais de ce type pas encore soldés sont recalculés.
     */
    public function update(TypeFraisRequest $request, TypeFrais $type, AppliquerNouveauTarif $appliquer): JsonResponse
    {
        $type->update($request->donnees());
        $resultat = $request->boolean('appliquer_aux_frais') ? $appliquer($type) : null;

        return (new TypeFraisResource($this->avecComptes($type)))->additional(['application' => $resultat])->response();
    }

    public function destroy(TypeFrais $type): Response|JsonResponse
    {
        $this->authorize('create', Paiement::class);

        if ($type->frais()->exists()) {
            return response()->json(['message' => 'Ce frais a déjà été appliqué à des doctorants : désactivez-le plutôt que de le supprimer.'], 409);
        }

        $type->delete();

        return response()->noContent();
    }
}
