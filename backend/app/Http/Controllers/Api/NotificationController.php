<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Doctorant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Cloche de l'en-tête : dernières notifications, nombre de non lues et, pour l'administration, nombre de doctorants en retard.
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => $user->notifications()->latest()->limit(30)->get()->map(fn (DatabaseNotification $n) => [
                'id' => $n->id,
                'titre' => $n->data['titre'] ?? '',
                'message' => $n->data['message'] ?? '',
                'lien' => $n->data['lien'] ?? null,
                'categorie' => $n->data['categorie'] ?? 'info',
                'lue' => $n->read_at !== null,
                'cree_le' => $n->created_at->toIso8601String(),
            ]),
            'non_lues' => $user->unreadNotifications()->count(),
            'en_retard' => $user->estSuperAdmin() ? Doctorant::query()->enRetardDePaiement()->count() : null,
        ]);
    }

    public function lue(Request $request, string $id): Response
    {
        $request->user()->notifications()->whereKey($id)->firstOrFail()->markAsRead();

        return response()->noContent();
    }

    public function toutLu(Request $request): Response
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->noContent();
    }
}
