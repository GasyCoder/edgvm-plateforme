<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reglage;
use App\Support\ReglagesActes;
use App\Support\ReglagesApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Réglages généraux de la plateforme (Paramètres > Application) et éléments publics (nom, logos, maintenance).
 */
class ReglagesApplicationController extends Controller
{
    /** Logos : nom dans le formulaire => clé partagée avec les actes */
    private const LOGOS = ['logo_edgvm' => 'logo_droite', 'logo_universite' => 'logo_gauche'];

    /**
     * Lu par l'interface avant la connexion : nom, logos, maintenance, apparence.
     */
    public function public(): JsonResponse
    {
        return response()->json(['data' => ReglagesApplication::public()]);
    }

    public function show(Request $request): JsonResponse
    {
        abort_unless($request->user()->estSuperAdmin(), 403);

        return response()->json(['data' => $this->donnees()]);
    }

    public function update(Request $request): JsonResponse
    {
        abort_unless($request->user()->estSuperAdmin(), 403);

        $request->validate([
            'app_nom' => ['required', 'string', 'max:60'],
            'app_sous_titre' => ['nullable', 'string', 'max:120'],
            'ecole_nom' => ['required', 'string', 'max:190'],
            'ecole_universite' => ['required', 'string', 'max:120'],
            'ecole_ministere' => ['required', 'string', 'max:255'],
            'ecole_arrete' => ['required', 'string', 'max:255'],
            'ecole_description' => ['nullable', 'string', 'max:3000'],
            'signataire_nom' => ['required', 'string', 'max:120'],
            'signataire_grade' => ['nullable', 'string', 'max:60'],
            'signataire_titre' => ['nullable', 'string', 'max:120'],
            'indexation' => ['boolean'],
            'maintenance_active' => ['boolean'],
            'maintenance_message' => ['required_if_accepted:maintenance_active', 'nullable', 'string', 'max:500'],
            'theme' => ['required', Rule::in(ReglagesApplication::THEMES)],
            'couleur' => ['required', Rule::in(ReglagesApplication::COULEURS)],
            ...collect(self::LOGOS)->keys()->mapWithKeys(fn (string $champ) => [$champ => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048']])->all(),
            ...collect(self::LOGOS)->keys()->mapWithKeys(fn (string $champ) => ['retirer_'.$champ => ['boolean']])->all(),
        ], [
            '*.required' => 'Ce champ est obligatoire.',
            'maintenance_message.required_if_accepted' => 'Indiquez le message affiché pendant la maintenance.',
            '*.mimes' => 'Image PNG, JPG ou WebP attendue.',
            '*.max' => 'Trop long.',
            'logo_edgvm.max' => "L'image ne doit pas dépasser 2 Mo.",
            'logo_universite.max' => "L'image ne doit pas dépasser 2 Mo.",
        ]);

        foreach (array_keys(ReglagesApplication::TEXTES) as $cle) {
            $champ = str_replace('.', '_', $cle);
            if ($request->has($champ)) {
                Reglage::definir($cle, $request->filled($champ) ? trim((string) $request->input($champ)) : null);
            }
        }

        foreach (array_keys(ReglagesApplication::DIRECTEUR) as $cle) {
            Reglage::definir('actes.'.$cle, $request->filled($cle) ? Str::squish((string) $request->input($cle)) : '');
        }

        Reglage::definir('app.indexation', $request->boolean('indexation') ? '1' : '0');
        Reglage::definir('maintenance.active', $request->boolean('maintenance_active') ? '1' : '0');
        Reglage::definir('apparence.theme', $request->string('theme')->toString());
        Reglage::definir('apparence.couleur', $request->string('couleur')->toString());

        foreach (self::LOGOS as $champ => $cle) {
            if ($request->hasFile($champ)) {
                $fichier = $request->file($champ);
                Reglage::definir('actes.'.$cle, $fichier->storeAs('reglages', $cle.'-'.Str::uuid().'.'.$fichier->extension(), ReglagesActes::DISQUE));
            } elseif ($request->boolean('retirer_'.$champ)) {
                Reglage::definir('actes.'.$cle, null);
            }
        }

        return response()->json(['data' => $this->donnees(), 'message' => "Réglages de l'application enregistrés."]);
    }

    /**
     * Logo de l'École ou de l'Université (public : page de connexion, favicon).
     */
    public function logo(string $cle): StreamedResponse|Response
    {
        abort_unless(in_array($cle, self::LOGOS, true), 404);

        $chemin = ReglagesActes::image($cle);
        if ($chemin && Storage::disk(ReglagesActes::DISQUE)->exists($chemin)) {
            return Storage::disk(ReglagesActes::DISQUE)->response($chemin, headers: ['Cache-Control' => 'public, max-age=86400']);
        }

        [$entete, $base64] = explode(',', (string) ReglagesActes::dataUri(null, $cle), 2);

        return response(base64_decode($base64), 200, ['Content-Type' => Str::between($entete, 'data:', ';'), 'Cache-Control' => 'public, max-age=86400']);
    }

    /**
     * robots.txt suivant le réglage d'indexation (fermé par défaut : plateforme interne).
     */
    public function robots(): Response
    {
        $contenu = ReglagesApplication::indexationAutorisee()
            ? "User-agent: *\nDisallow: /api/\nDisallow: /telechargements/\nDisallow: /verifier/\n"
            : "User-agent: *\nDisallow: /\n";

        return response($contenu, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * @return array<string, mixed>
     */
    private function donnees(): array
    {
        return [
            ...collect(ReglagesApplication::TEXTES)->keys()->mapWithKeys(fn (string $cle) => [str_replace('.', '_', $cle) => ReglagesApplication::texte($cle)])->all(),
            ...collect(ReglagesApplication::DIRECTEUR)->keys()->mapWithKeys(fn (string $cle) => [$cle => ReglagesApplication::directeur($cle)])->all(),
            'indexation' => ReglagesApplication::indexationAutorisee(),
            'maintenance_active' => ReglagesApplication::maintenanceActive(),
            'theme' => ReglagesApplication::theme(),
            'couleur' => ReglagesApplication::couleur(),
            'logos' => collect(self::LOGOS)->mapWithKeys(fn (string $cle, string $champ) => [$champ => [
                'url' => ReglagesApplication::public()['logos'][$champ === 'logo_edgvm' ? 'edgvm' : 'universite'],
                'personnalise' => ReglagesActes::image($cle) !== null,
            ]])->all(),
        ];
    }
}
