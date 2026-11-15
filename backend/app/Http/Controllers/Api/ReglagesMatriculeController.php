<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reglage;
use App\Support\NumeroMatricule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Modèle du matricule automatique des doctorants (Paramètres > Matricule), avec aperçu du prochain numéro.
 */
class ReglagesMatriculeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        abort_unless($request->user()->estSuperAdmin(), 403);
        $reglages = NumeroMatricule::reglages();

        return response()->json(['data' => [...$reglages, 'apercu' => NumeroMatricule::suivant($reglages)]]);
    }

    /**
     * Prochain matricule avec des réglages pas encore enregistrés.
     */
    public function apercu(Request $request): JsonResponse
    {
        abort_unless($request->user()->estSuperAdmin(), 403);
        $reglages = $this->valider($request);

        return response()->json(['data' => [
            'apercu' => NumeroMatricule::suivant($reglages),
            'suivants' => [NumeroMatricule::suivant($reglages, NumeroMatricule::annee($reglages['annee']) + 1)],
        ]]);
    }

    public function update(Request $request): JsonResponse
    {
        abort_unless($request->user()->estSuperAdmin(), 403);
        $reglages = $this->valider($request);

        foreach ($reglages as $cle => $valeur) {
            Reglage::definir('matricule.'.$cle, (string) $valeur);
        }

        return response()->json([
            'data' => [...$reglages, 'apercu' => NumeroMatricule::suivant($reglages)],
            'message' => 'Modèle de matricule enregistré. Il s\'applique aux prochains doctorants.',
        ]);
    }

    /**
     * @return array{format: string, annee: string, remise: string, depart: int, depart_annee: int}
     */
    private function valider(Request $request): array
    {
        $request->merge(['format' => is_string($request->input('format')) ? trim($request->input('format')) : $request->input('format')]);

        $donnees = $request->validate([
            'format' => ['required', 'string', 'max:40'],
            'annee' => ['required', Rule::in(['civile', 'universitaire'])],
            'remise' => ['required', Rule::in(['annuelle', 'jamais'])],
            'depart' => ['required', 'integer', 'min:1', 'max:99999999'],
        ], [
            'format.required' => 'Indiquez le modèle du matricule.',
            'depart.min' => 'Le numéro de départ commence à 1.',
            'depart.integer' => 'Le numéro de départ est un nombre entier.',
        ]);

        validator($donnees, [])->after(function (Validator $validator) use ($donnees): void {
            if ($erreur = NumeroMatricule::erreurFormat($donnees['format'], $donnees['remise'])) {
                $validator->errors()->add('format', $erreur);
            }
        })->validate();

        return [...$donnees, 'depart' => (int) $donnees['depart'], 'depart_annee' => NumeroMatricule::annee($donnees['annee'])];
    }
}
