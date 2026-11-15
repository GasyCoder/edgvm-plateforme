<?php

use App\Http\Controllers\Api\AnneeUniversitaireController;
use App\Http\Controllers\Api\AuthentificationController;
use App\Http\Controllers\Api\CompteController;
use App\Http\Controllers\Api\DoctorantController;
use App\Http\Controllers\Api\EquipeAccueilController;
use App\Http\Controllers\Api\ImportExportDoctorantsController;
use App\Http\Controllers\Api\InscriptionController;
use App\Http\Controllers\Api\PieceJointeController;
use App\Http\Controllers\Api\TableauDeBordController;
use Illuminate\Support\Facades\Route;

/*
 * API de l'interface Nuxt (session Sanctum). Les droits sont vérifiés par les policies.
 */
Route::middleware('throttle:6,1')->group(function (): void {
    Route::post('/connexion', [AuthentificationController::class, 'connexion'])->name('connexion');
    Route::post('/mot-de-passe/oublie', [AuthentificationController::class, 'motDePasseOublie'])->name('mot-de-passe.oublie');
    Route::post('/mot-de-passe/reinitialiser', [AuthentificationController::class, 'reinitialiserMotDePasse'])->name('mot-de-passe.reinitialiser');
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/deconnexion', [AuthentificationController::class, 'deconnexion'])->name('deconnexion');
    Route::get('/moi', [AuthentificationController::class, 'moi'])->name('moi');
    Route::put('/moi/mot-de-passe', [AuthentificationController::class, 'modifierMotDePasse'])->name('moi.mot-de-passe');

    Route::get('/tableau-de-bord', TableauDeBordController::class)->name('tableau-de-bord');

    Route::post('/doctorants/import', [ImportExportDoctorantsController::class, 'importer'])->name('doctorants.import');
    Route::apiResource('doctorants', DoctorantController::class)->except('destroy');
    Route::post('/doctorants/{doctorant}/inscriptions', [InscriptionController::class, 'store'])->name('inscriptions.store');
    Route::get('/doctorants/{doctorant}/pieces', [PieceJointeController::class, 'index'])->name('pieces.index');
    Route::post('/doctorants/{doctorant}/pieces', [PieceJointeController::class, 'store'])->name('pieces.store');
    Route::delete('/pieces/{piece}', [PieceJointeController::class, 'destroy'])->name('pieces.destroy');
    Route::put('/inscriptions/{inscription}', [InscriptionController::class, 'update'])->name('inscriptions.update');
    Route::delete('/inscriptions/{inscription}', [InscriptionController::class, 'destroy'])->name('inscriptions.destroy');

    Route::apiResource('equipes', EquipeAccueilController::class)->except('show')
        ->parameters(['equipes' => 'equipe']);
    Route::apiResource('annees', AnneeUniversitaireController::class)->only(['index', 'store', 'update']);

    Route::apiResource('comptes', CompteController::class)->only(['index', 'store', 'update']);
    Route::post('/comptes/{compte}/lien-acces', [CompteController::class, 'lienAcces'])->name('comptes.lien-acces');
});
