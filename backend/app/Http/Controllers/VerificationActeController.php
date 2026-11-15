<?php

namespace App\Http\Controllers;

use App\Models\Acte;
use Illuminate\Http\Response;

/**
 * Page publique de vérification d'un acte (QR code imprimé sur le document) : valide ou annulé, sans autre donnée personnelle.
 */
class VerificationActeController extends Controller
{
    public function __invoke(string $code): Response
    {
        $acte = Acte::query()->with(['modele', 'doctorant'])->where('code_verification', $code)->first();

        return response()->view('actes.verification', ['acte' => $acte], $acte ? 200 : 404);
    }
}
