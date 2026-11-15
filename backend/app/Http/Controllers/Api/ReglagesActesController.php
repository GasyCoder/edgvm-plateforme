<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ModeleActe;
use App\Models\Reglage;
use App\Support\ReglagesActes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Signataire, ville, logos et signature scannée des actes.
 */
class ReglagesActesController extends Controller
{
    public function show(): JsonResponse
    {
        $this->authorize('create', ModeleActe::class);

        return response()->json(['data' => $this->donnees()]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorize('create', ModeleActe::class);
        $request->validate([
            ReglagesActes::AFFICHER_NOM => ['boolean'],
            'signataire_nom' => ['nullable', 'required_if_accepted:'.ReglagesActes::AFFICHER_NOM, 'string', 'max:120'],
            'signataire_fonction' => ['required', 'string', 'max:120'],
            'signataire_titre' => ['nullable', 'string', 'max:120'],
            'ville' => ['required', 'string', 'max:80'],
            ...collect(ReglagesActes::IMAGES)->mapWithKeys(fn (string $cle) => [$cle => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:2048']])->all(),
            ...collect(ReglagesActes::IMAGES)->mapWithKeys(fn (string $cle) => ['retirer_'.$cle => ['boolean']])->all(),
        ], ['*.mimes' => 'Image PNG ou JPG attendue (PNG à fond transparent pour la signature).', '*.max' => "L'image ne doit pas dépasser 2 Mo."], [
            'signataire_nom' => 'nom du signataire', 'signataire_fonction' => 'fonction', 'logo_gauche' => 'logo de gauche', 'logo_droite' => 'logo de droite', 'signature' => 'signature',
        ]);

        Reglage::definir('actes.'.ReglagesActes::AFFICHER_NOM, $request->boolean(ReglagesActes::AFFICHER_NOM) ? '1' : '0');

        foreach (array_keys(ReglagesActes::TEXTES) as $cle) {
            Reglage::definir('actes.'.$cle, $request->filled($cle) ? $request->string($cle)->trim()->toString() : null);
        }

        foreach (ReglagesActes::IMAGES as $cle) {
            if ($request->hasFile($cle)) {
                $fichier = $request->file($cle);
                Reglage::definir('actes.'.$cle, $fichier->storeAs('reglages', $cle.'-'.Str::uuid().'.'.$fichier->extension(), ReglagesActes::DISQUE));
            } elseif ($request->boolean('retirer_'.$cle)) {
                Reglage::definir('actes.'.$cle, null);
            }
        }

        return response()->json(['data' => $this->donnees(), 'message' => 'Réglages des actes enregistrés.']);
    }

    /**
     * Image actuelle (ou par défaut) pour l'affichage dans l'interface.
     */
    public function image(string $cle): StreamedResponse|JsonResponse
    {
        $this->authorize('create', ModeleActe::class);
        abort_unless(in_array($cle, ReglagesActes::IMAGES, true), 404);

        $chemin = ReglagesActes::image($cle);
        if ($chemin && Storage::disk(ReglagesActes::DISQUE)->exists($chemin)) {
            return Storage::disk(ReglagesActes::DISQUE)->response($chemin, headers: ['Cache-Control' => 'private, no-store']);
        }

        $defaut = ReglagesActes::dataUri(null, $cle);
        abort_unless($defaut, 404);
        [$entete, $base64] = explode(',', $defaut, 2);

        return response()->streamDownload(fn () => print (base64_decode($base64)), $cle, [
            'Content-Type' => Str::between($entete, 'data:', ';'),
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }

    /**
     * @return array<string, mixed>
     */
    private function donnees(): array
    {
        return [
            ...collect(ReglagesActes::TEXTES)->keys()->mapWithKeys(fn (string $cle) => [$cle => ReglagesActes::texte($cle)])->all(),
            ReglagesActes::AFFICHER_NOM => ReglagesActes::afficherNom(),
            'images' => collect(ReglagesActes::IMAGES)->mapWithKeys(fn (string $cle) => [$cle => [
                'personnalisee' => ReglagesActes::image($cle) !== null,
                'existe' => ReglagesActes::image($cle) !== null || $cle !== 'signature',
            ]])->all(),
        ];
    }
}
