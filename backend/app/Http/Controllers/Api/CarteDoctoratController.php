<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Doctorant;
use App\Support\CarteDoctorat;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Cartes de doctorat en PDF : une carte, ou celles d'une sélection (une par page) pour l'impression.
 */
class CarteDoctoratController extends Controller
{
    public function une(Doctorant $doctorant): Response
    {
        $this->authorize('update', $doctorant);

        return $this->pdf(collect([$doctorant]), 'carte-doctorat-'.$doctorant->matricule.'.pdf');
    }

    public function lot(Request $request): Response
    {
        $request->validate(['ids' => ['required', 'array', 'min:1', 'max:300'], 'ids.*' => ['string']]);
        $doctorants = Doctorant::query()->whereIn('uuid', (array) $request->input('ids'))->orderBy('nom')->orderBy('prenoms')->get();
        abort_if($doctorants->isEmpty(), 404);
        $doctorants->each(fn (Doctorant $doctorant) => $this->authorize('update', $doctorant));

        return $this->pdf($doctorants, 'cartes-doctorat-'.now()->format('Y-m-d-His').'.pdf');
    }

    private function pdf($doctorants, string $nom): Response
    {
        return response(CarteDoctorat::generer($doctorants), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nom.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
