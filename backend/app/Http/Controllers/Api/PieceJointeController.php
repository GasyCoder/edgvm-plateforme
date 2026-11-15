<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PieceJointeRequest;
use App\Http\Resources\PieceJointeResource;
use App\Models\Doctorant;
use App\Models\PieceJointe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pièces jointes du dossier doctorant, stockées dans storage/app/private (jamais accessibles directement).
 */
class PieceJointeController extends Controller
{
    public function index(Request $request, Doctorant $doctorant): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [PieceJointe::class, $doctorant]);

        $pieces = $doctorant->piecesJointes()
            ->with(['auteur', 'doctorant'])
            ->latest()
            ->get()
            ->filter(fn (PieceJointe $piece) => $request->user()->can('view', $piece))
            ->values();

        return PieceJointeResource::collection($pieces);
    }

    public function store(PieceJointeRequest $request, Doctorant $doctorant): JsonResponse
    {
        $fichier = $request->file('fichier');
        $extension = strtolower($fichier->getClientOriginalExtension() ?: $fichier->extension());
        $chemin = $fichier->storeAs("pieces/{$doctorant->uuid}", Str::uuid().'.'.$extension, PieceJointe::DISQUE);

        $piece = $doctorant->piecesJointes()->create([
            'categorie' => $request->input('categorie'),
            'titre' => $request->filled('titre') ? $request->string('titre')->trim()->toString() : pathinfo($fichier->getClientOriginalName(), PATHINFO_FILENAME),
            'nom_original' => $fichier->getClientOriginalName(),
            'chemin' => $chemin,
            'type_mime' => $fichier->getMimeType() ?? 'application/octet-stream',
            'taille' => $fichier->getSize(),
            'ajoute_par' => $request->user()->id,
        ]);

        return (new PieceJointeResource($piece->load(['auteur', 'doctorant'])))->response()->setStatusCode(201);
    }

    /**
     * « ?afficher=1 » ouvre un PDF ou une image dans le navigateur ; sinon le fichier se télécharge.
     */
    public function telecharger(Request $request, PieceJointe $piece): StreamedResponse
    {
        $this->authorize('view', $piece);
        abort_unless(Storage::disk(PieceJointe::DISQUE)->exists($piece->chemin), 404, 'Fichier introuvable.');

        $disposition = $request->boolean('afficher') && $piece->peutSAfficher() ? 'inline' : 'attachment';

        return Storage::disk(PieceJointe::DISQUE)->response($piece->chemin, $piece->nom_original, [
            'Content-Type' => $piece->type_mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], $disposition);
    }

    public function destroy(PieceJointe $piece): Response
    {
        $this->authorize('delete', $piece);

        $piece->delete();

        return response()->noContent();
    }
}
