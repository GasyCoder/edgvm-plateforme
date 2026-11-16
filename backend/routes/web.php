<?php

use App\Http\Controllers\Api\ActeController;
use App\Http\Controllers\Api\CandidatController;
use App\Http\Controllers\Api\CarteDoctoratController;
use App\Http\Controllers\Api\ImportExportDoctorantsController;
use App\Http\Controllers\Api\PaiementController;
use App\Http\Controllers\Api\PhotoDoctorantController;
use App\Http\Controllers\Api\PhotoUtilisateurController;
use App\Http\Controllers\Api\PieceJointeController;
use App\Http\Controllers\Api\ReglagesActesController;
use App\Http\Controllers\Api\ReglagesApplicationController;
use App\Http\Controllers\InterfaceController;
use App\Http\Controllers\VerificationActeController;
use Illuminate\Support\Facades\Route;

/*
 * Téléchargements ouverts par de simples liens (nouvel onglet, favori) : session classique,
 * sans dépendre de l'en-tête Referer qu'utilise Sanctum pour l'API.
 */
Route::middleware('auth')->prefix('telechargements')->group(function (): void {
    Route::get('/doctorants.xlsx', [ImportExportDoctorantsController::class, 'exporterExcel'])->name('doctorants.export-excel');
    Route::get('/doctorants.csv', [ImportExportDoctorantsController::class, 'exporter'])->name('doctorants.export');
    Route::get('/candidats.{format}', [CandidatController::class, 'exporter'])->whereIn('format', ['xlsx', 'csv'])->name('candidats.export');
    Route::get('/modele-import-candidats.csv', [ImportExportDoctorantsController::class, 'modeleCandidats'])->name('candidats.modele-import');
    Route::get('/modele-import-doctorants.csv', [ImportExportDoctorantsController::class, 'modele'])->name('doctorants.modele-import');
    Route::get('/utilisateurs/{utilisateur}/photo', [PhotoUtilisateurController::class, 'afficher'])->name('utilisateurs.photo');
    Route::get('/doctorants/{doctorant}/photo', [PhotoDoctorantController::class, 'afficher'])->name('doctorants.photo');
    Route::get('/doctorants/{doctorant}/carte.pdf', [CarteDoctoratController::class, 'une'])->name('doctorants.carte');
    Route::get('/cartes-doctorat.pdf', [CarteDoctoratController::class, 'lot'])->name('doctorants.cartes');
    Route::get('/pieces/{piece}', [PieceJointeController::class, 'telecharger'])->name('pieces.telecharger');
    Route::get('/actes.pdf', [ActeController::class, 'pdfLot'])->name('actes.pdf-lot');
    Route::get('/actes/{acte}.pdf', [ActeController::class, 'pdf'])->name('actes.pdf');
    Route::get('/paiements/{paiement}/recu.pdf', [PaiementController::class, 'recu'])->name('paiements.recu');
    Route::get('/paiements/{paiement}/justificatif', [PaiementController::class, 'voirJustificatif'])->name('paiements.voir-justificatif');
    Route::get('/paiements.csv', [PaiementController::class, 'exporter'])->name('paiements.export');
    Route::get('/paiements/recus.pdf', [PaiementController::class, 'recus'])->name('paiements.recus');
    Route::get('/situation-paiements.csv', [PaiementController::class, 'exporterSituation'])->name('paiements.export-situation');
    Route::get('/reglages-actes/{cle}', [ReglagesActesController::class, 'image'])->name('reglages-actes.image');
});

// Vérification publique d'un acte (QR code imprimé)
Route::get('/marque/{cle}', [ReglagesApplicationController::class, 'logo'])->name('marque.logo');
Route::get('/robots.txt', [ReglagesApplicationController::class, 'robots'])->name('robots');
Route::get('/verifier/{code}', VerificationActeController::class)->middleware('throttle:30,1')->name('actes.verifier');

// L'interface Nuxt gère toutes les pages ; l'API est dans routes/api.php.
Route::get('/', InterfaceController::class)->name('interface');
Route::fallback(InterfaceController::class);
