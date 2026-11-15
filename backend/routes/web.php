<?php

use App\Http\Controllers\Api\ActeController;
use App\Http\Controllers\Api\ImportExportDoctorantsController;
use App\Http\Controllers\Api\PieceJointeController;
use App\Http\Controllers\Api\ReglagesActesController;
use App\Http\Controllers\InterfaceController;
use App\Http\Controllers\VerificationActeController;
use Illuminate\Support\Facades\Route;

/*
 * Téléchargements ouverts par de simples liens (nouvel onglet, favori) : session classique,
 * sans dépendre de l'en-tête Referer qu'utilise Sanctum pour l'API.
 */
Route::middleware('auth')->prefix('telechargements')->group(function (): void {
    Route::get('/doctorants.csv', [ImportExportDoctorantsController::class, 'exporter'])->name('doctorants.export');
    Route::get('/modele-import-doctorants.csv', [ImportExportDoctorantsController::class, 'modele'])->name('doctorants.modele-import');
    Route::get('/pieces/{piece}', [PieceJointeController::class, 'telecharger'])->name('pieces.telecharger');
    Route::get('/actes.pdf', [ActeController::class, 'pdfLot'])->name('actes.pdf-lot');
    Route::get('/actes/{acte}.pdf', [ActeController::class, 'pdf'])->name('actes.pdf');
    Route::get('/reglages-actes/{cle}', [ReglagesActesController::class, 'image'])->name('reglages-actes.image');
});

// Vérification publique d'un acte (QR code imprimé)
Route::get('/verifier/{code}', VerificationActeController::class)->middleware('throttle:30,1')->name('actes.verifier');

// L'interface Nuxt gère toutes les pages ; l'API est dans routes/api.php.
Route::get('/', InterfaceController::class)->name('interface');
Route::fallback(InterfaceController::class);
