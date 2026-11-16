<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PhotoUtilisateur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Photo de profil : la sienne (Mon compte), ou celle d'un compte (administration, Utilisateurs).
 */
class PhotoUtilisateurController extends Controller
{
    public function enregistrerMienne(Request $request): JsonResponse
    {
        return $this->enregistrer($request, $request->user());
    }

    public function supprimerMienne(Request $request): JsonResponse
    {
        PhotoUtilisateur::supprimer($request->user());

        return response()->json(['data' => ['photo_url' => PhotoUtilisateur::url($request->user()->load('doctorant'))], 'message' => 'Photo retirée.']);
    }

    public function enregistrerCompte(Request $request, User $compte): JsonResponse
    {
        $this->authorize('update', $compte);

        return $this->enregistrer($request, $compte);
    }

    public function supprimerCompte(User $compte): JsonResponse
    {
        $this->authorize('update', $compte);
        PhotoUtilisateur::supprimer($compte);

        return response()->json(['data' => ['photo_url' => PhotoUtilisateur::url($compte->load('doctorant'))], 'message' => 'Photo retirée.']);
    }

    /**
     * Visible par la personne elle-même et par le personnel (administration, membres d'équipe).
     */
    public function afficher(Request $request, User $utilisateur): StreamedResponse
    {
        $moi = $request->user();
        abort_unless($moi->is($utilisateur) || $moi->estSuperAdmin() || $moi->estMembreEad(), 403);
        $utilisateur->refresh();
        abort_unless($utilisateur->photo_chemin && Storage::disk(PhotoUtilisateur::DISQUE)->exists($utilisateur->photo_chemin), 404);

        return Storage::disk(PhotoUtilisateur::DISQUE)->response($utilisateur->photo_chemin, 'photo.jpg', [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    private function enregistrer(Request $request, User $user): JsonResponse
    {
        $request->validate(
            ['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:min_width=100,min_height=100']],
            [
                'photo.required' => 'Choisissez une photo.',
                'photo.image' => 'Le fichier doit être une image.',
                'photo.mimes' => 'Formats acceptés : JPEG, PNG ou WebP.',
                'photo.max' => 'La photo dépasse 8 Mo.',
                'photo.dimensions' => 'La photo est trop petite (100 pixels au moins de chaque côté).',
            ],
        );

        try {
            PhotoUtilisateur::enregistrer($user, $request->file('photo'));
        } catch (RuntimeException) {
            return response()->json(['message' => 'Image illisible.', 'errors' => ['photo' => ['Image illisible.']]], 422);
        }

        return response()->json(['data' => ['photo_url' => PhotoUtilisateur::url($user)], 'message' => 'Photo de profil enregistrée.'], 201);
    }
}
