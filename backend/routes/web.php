<?php

use App\Http\Controllers\Api\ImportExportDoctorantsController;
use App\Http\Controllers\Api\PieceJointeController;
use App\Http\Controllers\InterfaceController;
use Illuminate\Support\Facades\Route;

/*
 * Téléchargements ouverts par de simples liens (nouvel onglet, favori) : session classique,
 * sans dépendre de l'en-tête Referer qu'utilise Sanctum pour l'API.
 */
Route::middleware('auth')->prefix('telechargements')->group(function (): void {
    Route::get('/doctorants.csv', [ImportExportDoctorantsController::class, 'exporter'])->name('doctorants.export');
    Route::get('/modele-import-doctorants.csv', [ImportExportDoctorantsController::class, 'modele'])->name('doctorants.modele-import');
    Route::get('/pieces/{piece}', [PieceJointeController::class, 'telecharger'])->name('pieces.telecharger');
});

// L'interface Nuxt gère toutes les pages ; l'API est dans routes/api.php.
Route::get('/', InterfaceController::class)->name('interface');
Route::fallback(InterfaceController::class);
