<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Doctorant;
use App\Support\PhotoDoctorant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Photo d'identité du doctorant (carte de doctorat).
 */
class PhotoDoctorantController extends Controller
{
    public function store(Request $request, Doctorant $doctorant): JsonResponse
    {
        $this->authorize('update', $doctorant);
        $request->validate(
            ['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:min_width=150,min_height=150']],
            [
                'photo.required' => 'Choisissez une photo.',
                'photo.image' => 'Le fichier doit être une image.',
                'photo.mimes' => 'Formats acceptés : JPEG, PNG ou WebP.',
                'photo.max' => 'La photo dépasse 8 Mo.',
                'photo.dimensions' => 'La photo est trop petite (150 pixels au moins de chaque côté).',
            ],
        );

        try {
            PhotoDoctorant::enregistrer($doctorant, $request->file('photo'));
        } catch (RuntimeException) {
            return response()->json(['message' => 'Image illisible.', 'errors' => ['photo' => ['Image illisible.']]], 422);
        }

        return response()->json(['data' => ['photo_url' => $this->url($doctorant)], 'message' => 'Photo enregistrée.'], 201);
    }

    public function destroy(Doctorant $doctorant): JsonResponse
    {
        $this->authorize('update', $doctorant);
        PhotoDoctorant::supprimer($doctorant);

        return response()->json(['data' => ['photo_url' => null], 'message' => 'Photo retirée.']);
    }

    public function afficher(Doctorant $doctorant): StreamedResponse
    {
        $this->authorize('view', $doctorant);
        abort_unless($doctorant->photo_chemin && Storage::disk(PhotoDoctorant::DISQUE)->exists($doctorant->photo_chemin), 404);

        return Storage::disk(PhotoDoctorant::DISQUE)->response($doctorant->photo_chemin, 'photo.jpg', [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    private function url(Doctorant $doctorant): string
    {
        return '/telechargements/doctorants/'.$doctorant->uuid.'/photo?v='.substr(md5((string) $doctorant->photo_chemin), 0, 8);
    }
}
